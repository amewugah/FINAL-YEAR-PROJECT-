<?php

namespace App\Http\Controllers;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Conversation;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpPresentation\IOFactory as PptParser;
use App\Helpers\GeminiHelper;
use App\Helpers\BroadcastHelper;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    private function isGroupMember(Group $group, int $userId): bool
    {
        return $group->users()->where('users.id', $userId)->exists();
    }

    private function requireGroupMembership(Group $group)
    {
        $userId = (int) auth()->id();
        if (!$this->isGroupMember($group, $userId)) {
            return response()->json(['message' => 'You are not a member of this group.'], 403);
        }

        return null;
    }

    private function resolveOwnerId(Group $group): ?int
    {
        if (!empty($group->owner_id)) {
            return (int) $group->owner_id;
        }

        // Fallback for legacy groups created before owner_id existed.
        $fallbackOwnerId = $group->users()->orderBy('users.id')->value('users.id');
        if (!empty($fallbackOwnerId)) {
            $group->owner_id = (int) $fallbackOwnerId;
            $group->save();
            return (int) $fallbackOwnerId;
        }

        return null;
    }

    private function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Group::where('invite_code', $code)->exists());

        return $code;
    }

    private function ensureInviteCode(Group $group): string
    {
        if (!empty($group->invite_code)) {
            return (string) $group->invite_code;
        }

        $group->invite_code = $this->generateInviteCode();
        $group->save();

        return (string) $group->invite_code;
    }

     /**
     * Get conversations for a specific group.
     *
     * @param int $groupId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGroupConversations($groupId)
    {
        $group = Group::findOrFail($groupId); // Ensure the group exists
        if ($membershipError = $this->requireGroupMembership($group)) {
            return $membershipError;
        }

        $conversations = Conversation::where('group_id', $groupId)
            ->with('chat') // Eager load the related chat if needed
            ->get();

        return response()->json($conversations);
    }

    public function getGroupMembers($groupId)
    {
        $group = Group::with('users:id,name,email')->findOrFail($groupId);
        if ($membershipError = $this->requireGroupMembership($group)) {
            return $membershipError;
        }
        $inviteCode = $this->ensureInviteCode($group);
        $ownerId = $this->resolveOwnerId($group);
        return response()->json([
            'members' => $group->users,
            'owner_id' => $ownerId,
            'invite_code' => $inviteCode,
            'current_user_id' => auth()->id(),
        ]);
    }
    // getting all groups
    public function getAllGroups()
    {
        $userId = (int) auth()->id();
        $groups = Group::where(function ($query) use ($userId) {
            $query->where('owner_id', $userId)
                ->orWhereHas('users', function ($memberQuery) use ($userId) {
                    $memberQuery->where('users.id', $userId);
                });
        })->get();
        $groups->each(function (Group $group) {
            $this->ensureInviteCode($group);

            // Backfill legacy owner membership so owner always appears as a member.
            $ownerId = $this->resolveOwnerId($group);
            if (!empty($ownerId) && !$this->isGroupMember($group, (int) $ownerId)) {
                $group->users()->syncWithoutDetaching([(int) $ownerId]);
            }
        });

        return response()->json($groups);
    }

    // creating a group
    public function createGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $group = Group::create([
            'name' => $request->name,
            'description' => $request->description,
            'owner_id' => auth()->id(),
            'invite_code' => $this->generateInviteCode(),
        ]);
        if (auth()->check()) {
            $group->users()->syncWithoutDetaching([auth()->id()]);
        }

        return response()->json([
            'message' => 'Group created successfully',
            'group' => $group,
            'invite_code' => $group->invite_code,
        ]);
    }

    // joining a group
    public function joinGroup($groupId)
{
    return response()->json(['message' => 'Please join groups using invite code.'], 403);
}

public function joinGroupByCode(Request $request)
{
    $request->validate([
        'invite_code' => 'required|string',
    ]);

    $group = Group::where('invite_code', strtoupper(trim($request->input('invite_code'))))->first();
    if (!$group) {
        return response()->json(['message' => 'Invalid invite code.'], 404);
    }

    $userId = (int) auth()->id();
    if ($this->isGroupMember($group, $userId)) {
        return response()->json(['message' => 'You are already in this group.', 'group' => $group], 200);
    }

    $group->users()->attach($userId);

    return response()->json([
        'message' => 'Joined group successfully.',
        'group' => $group,
    ]);
}

public function addUserToGroup(Request $request, $groupId)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $group = Group::findOrFail($groupId);
    if ($membershipError = $this->requireGroupMembership($group)) {
        return $membershipError;
    }
    $ownerId = $this->resolveOwnerId($group);
    if ((int) $ownerId !== (int) auth()->id()) {
        return response()->json(['message' => 'Only the group owner can add members.'], 403);
    }
    $userToAdd = User::where('email', $request->input('email'))->firstOrFail();

    $alreadyInGroup = $group->users()->where('user_id', $userToAdd->id)->exists();
    if ($alreadyInGroup) {
        return response()->json(['message' => 'User is already in this group.'], 200);
    }

    $group->users()->attach($userToAdd->id);

    return response()->json([
        'message' => 'User added to group successfully.',
        'user' => [
            'id' => $userToAdd->id,
            'name' => $userToAdd->name,
            'email' => $userToAdd->email,
        ],
    ]);
}

public function removeUserFromGroup($groupId, $userId)
{
    $group = Group::findOrFail($groupId);
    if ($membershipError = $this->requireGroupMembership($group)) {
        return $membershipError;
    }
    $ownerId = $this->resolveOwnerId($group);
    if ((int) $ownerId !== (int) auth()->id()) {
        return response()->json(['message' => 'Only the group owner can remove members.'], 403);
    }
    $group->users()->detach($userId);

    return response()->json([
        'message' => 'User removed from group successfully.',
    ]);
}

// upload slides to group
public function uploadSlides(Request $request, $groupId)
{
    $request->validate(['slides' => 'required|file|mimes:pdf,ppt,pptx,doc,docx,xls,xlsx,txt,csv']);
    $group = Group::findOrFail($groupId);
    if ($membershipError = $this->requireGroupMembership($group)) {
        return $membershipError;
    }

    $folder = "groups/{$groupId}";
    $originalName = $request->file('slides')->getClientOriginalName();
    $filePath = $request->file('slides')->storeAs($folder, $originalName, 'local');
    return response()->json(['message' => 'Slides uploaded successfully', 'path' => $filePath]);
}

public function listGroupSlides($groupId)
{
    $group = Group::findOrFail($groupId);
    if ($membershipError = $this->requireGroupMembership($group)) {
        return $membershipError;
    }
    $ownerId = $this->resolveOwnerId($group);
    $folder = "groups/{$groupId}";
    $files = Storage::disk('local')->exists($folder)
        ? Storage::disk('local')->files($folder)
        : [];

    return response()->json([
        'slides' => array_map(function ($path) {
            return [
                'file_path' => $path,
                'file_name' => basename($path),
            ];
        }, $files),
        'owner_id' => $ownerId,
        'current_user_id' => auth()->id(),
    ]);
}

public function deleteGroupSlide(Request $request, $groupId)
{
    $request->validate([
        'file_name' => 'required|string',
    ]);

    $group = Group::findOrFail($groupId);
    if ($membershipError = $this->requireGroupMembership($group)) {
        return $membershipError;
    }
    $ownerId = $this->resolveOwnerId($group);
    if ((int) $ownerId !== (int) auth()->id()) {
        return response()->json(['message' => 'Only the group owner can delete slides.'], 403);
    }

    $fileName = basename($request->input('file_name'));
    $path = "groups/{$groupId}/{$fileName}";

    if (!Storage::disk('local')->exists($path)) {
        return response()->json(['message' => 'Slide file not found.'], 404);
    }

    Storage::disk('local')->delete($path);
    return response()->json(['message' => 'Group slide deleted successfully.']);
}

// chat in group

public function groupChat(Request $request, $groupId)
{
    $request->validate(['query' => 'required|string']);

    $user = auth()->user();
    $group = Group::findOrFail($groupId);
    if ($membershipError = $this->requireGroupMembership($group)) {
        return $membershipError;
    }
    $query = trim($request->input('query'));

    // Accept AI command variants: /ask, /ask:, /askai, /askai:
    $isAiCommand = preg_match('/^\/ask(ai)?\s*:?\s*/i', $query) === 1;
    if ($isAiCommand) {
        $query = preg_replace('/^\/ask(ai)?\s*:?\s*/i', '', $query);
        $query = trim((string) $query);
        if ($query === '') {
            return response()->json([
                'message' => 'Please add a question after /ask.',
            ], 422);
        }

        // Extract text from slides
        $slidePath = storage_path("app/private/groups/{$groupId}");
        $allText = $this->extractSlidesText($slidePath);

         // Use Gemini (free tier friendly).
         $response = GeminiHelper::getNlpResponse($query, $allText);

         $conversation = Conversation::create([
            'group_id' => $groupId,
            'query' => $query,
            'response' => $response,
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);
         // Broadcast the new conversation message to the group channel

         $broadcaster = new BroadcastHelper();
         $broadcaster->sendMessageToGroupChat($groupId, $conversation->toArray());

    // Dispatch the event
        // event(new NewMessage($conversation));
        return response()->json(['conversation' => $conversation]);
    }

    // Save non-AI message to group chat
    $conversation = $group->conversations()->create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'query' => $query,
    ]);

    $broadcaster = new BroadcastHelper();
    $broadcaster->sendMessageToGroupChat($groupId, $conversation->toArray());

    return response()->json(['conversation' => $conversation]);
}

  /**
     * Extract text from supported files in the folder.
     */
    private function extractSlidesText($folderPath)
    {
        $allText = '';
        $files = File::files($folderPath);

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            $fileName = $file->getFilename();
            $ext = strtolower($file->getExtension());

            // Ignore temporary/lock files created by Office apps.
            if (str_starts_with($fileName, '~$') || str_starts_with($fileName, '.~lock')) {
                continue;
            }

            try {
                if ($ext === 'pdf') {
                    $parser = new PdfParser();
                    $pdf = $parser->parseFile($filePath);
                    $allText .= $pdf->getText() . "\n";
                    continue;
                }

                if ($ext === 'ppt' || $ext === 'pptx') {
                    $pptReader = PptParser::createReader('PowerPoint2007');
                    $presentation = $pptReader->load($filePath);
                    foreach ($presentation->getAllSlides() as $slide) {
                        foreach ($slide->getShapeCollection() as $shape) {
                            if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                                $allText .= $shape->getPlainText() . "\n";
                            }
                        }
                    }
                    continue;
                }

                if ($ext === 'docx' || $ext === 'xlsx') {
                    $allText .= $this->extractFromOpenXml($filePath, $ext) . "\n";
                    continue;
                }

                if ($ext === 'txt' || $ext === 'csv') {
                    $allText .= file_get_contents($filePath) . "\n";
                    continue;
                }

                if ($ext === 'doc' || $ext === 'xls') {
                    $allText .= $this->extractFromLegacyBinary($filePath) . "\n";
                }
            } catch (\Throwable $e) {
                Log::warning('Skipping unreadable group document during extraction.', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $allText;
    }

    private function extractFromOpenXml($filePath, $ext)
    {
        $zip = new \ZipArchive();
        $text = '';
        if ($zip->open($filePath) !== true) {
            return $text;
        }

        $prefix = $ext === 'docx' ? 'word/' : 'xl/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, $prefix) && str_ends_with($name, '.xml')) {
                $content = $zip->getFromIndex($i);
                $text .= ' ' . strip_tags(str_replace(['</w:p>', '</a:p>', '</row>'], "\n", $content));
            }
        }
        $zip->close();
        return preg_replace('/\s+/', ' ', $text);
    }

    private function extractFromLegacyBinary($filePath)
    {
        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return '';
        }
        $text = preg_replace('/[^[:print:]\r\n\t]/', ' ', $raw);
        return preg_replace('/\s+/', ' ', $text);
    }
}
