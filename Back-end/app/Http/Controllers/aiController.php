<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Conversation;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpPresentation\IOFactory as PptParser;
use App\Helpers\GeminiHelper;

class aiController extends Controller
{
    /**
     * Create a new chat
     */
    public function createChat(Request $request)
{
    // Validate the input query
    $request->validate([
        'query' => 'required|string',
    ]);

    $userId = auth()->id();
    $query = $request->input('query');

    // Generate the chat title based on the query
    $chatTitle = $this->generateChatTitle($query);

    // Create a new chat
    $chat = Chat::create([
        'user_id' => $userId,
        'chat_title' => $chatTitle,
        'query' => $query,
        'response' => '',
    ]);

    return response()->json([
        'chat_id' => $chat->id,
        'chat_title'=>$chat->chat_title,
        'message' => 'New chat created successfully.',
    ]);
}
    public function getOrCreateChat(Request $request)
    {
        $userId = auth()->id();

        // Retrieve the latest chat or create a new one if none exists or a new one is requested
        $chat = Chat::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$chat || $request->input('new_chat')) {
            return $this->createChat($request);
        }

        // // If continuing the current chat, append the conversation
        // return $this->updateChat($request, $chat->id);
    }

    /**
     * Get all chats of the logged-in user
     */
    public function getUserChats()
    {
        $userId = auth()->id();
        $chats = Chat::where('user_id', $userId)
            ->with('conversations')
            ->get();

        return response()->json($chats);
    }

    /**
     * Get a specific chat by ID with conversations
     */
    public function showChat($id)
    {
        $userId = auth()->id();
        $chat = Chat::where('id', $id)
            ->where('user_id', $userId)
            ->with('conversations')
            ->firstOrFail();

        return response()->json($chat);
    }

    /**
     * Append a query and response to an existing chat or create a new chat
     */
    public function updateChat(Request $request)
{
 // Validate incoming request data
$request->validate([
    'chat_id' => 'required|integer|exists:chats,id', // Validate chat_id is required, an integer, and exists in the chats table
    'query' => 'required|string',
]);

// Retrieve the chat ID from the request
$chatId = $request->input('chat_id');
$chat = Chat::findOrFail($chatId);

// Ensure the authenticated user owns the chat
if ($chat->user_id != auth()->id()) {
    return response()->json(['error' => 'Unauthorized'], 403);
}

// Generate and save the chat title if it's currently null
if (empty($chat->chat_title)) {
    $chat->chat_title = $this->generateChatTitle($request->input('query'));
    $chat->save(); // Save the updated chat with the new title
}

// Get the user's ID to locate their specific folder
$userId = auth()->id();
$slidePaths = [
    storage_path("app/private/slides/user_{$userId}"),
    storage_path("app/private/private/slides/user_{$userId}"),
];

$hasSlides = false;
foreach ($slidePaths as $path) {
    if (file_exists($path) && count(scandir($path)) > 2) {
        $hasSlides = true;
        break;
    }
}

// Check if any supported slide folder exists and contains files.
if (!$hasSlides) { // `> 2` because `scandir` returns `.` and `..` even if empty
    return response()->json([
        'message' => 'Please upload slides to start the conversation with the AI.',
        'slide_path' => $slidePaths[0]
    ], 200);
}

// Extract text from the user's slides
$allText = '';
foreach ($slidePaths as $path) {
    if (file_exists($path) && count(scandir($path)) > 2) {
        $allText .= "\n" . $this->extractSlidesText($path);
    }
}

// Get the AI-generated response
$query = $request->input('query');
// Use Gemini (free tier friendly).
$response = GeminiHelper::getNlpResponse($query, $allText);

// Create a new conversation in the chat
$conversation = Conversation::create([
    'chat_id' => $chatId,
    'query' => $query,
    'response' => $response,
]);

// Return the response to the user
// return response()->json([
//     'message' => 'Conversation created successfully.',
//     'conversation' => $conversation,
//     'ai_response' => $response
// ], 201);


return response()->json([
    'chat_id' => $chat->id,
    'conversation' => $conversation,
]);

}

/**
 * Generate a more descriptive chat title based on the content of the query.
 *
 * @param string $query
 * @return string
 */
private function generateChatTitle($query)
{
    // Handle simple greeting queries
    $greetings = ['hello', 'hi', 'hey'];
    $lowercaseQuery = strtolower($query);

    if (in_array($lowercaseQuery, $greetings)) {
        return 'Greeting Conversation';
    }

    // List of words to ignore in the chat title
    $ignoreWords = ['a', 'an', 'the', 'and', 'or', 'in', 'on', 'at', 'with', 'to', 'for', 'of', 'make'];

    // Split the query into words and filter out the ignored words
    $words = explode(' ', $lowercaseQuery);
    $filteredWords = array_filter($words, function ($word) use ($ignoreWords) {
        return !in_array($word, $ignoreWords);
    });

    // Capitalize the remaining words to create the title
    $titleWords = array_map('ucfirst', $filteredWords);

    // Join the words to form the chat title
    $chatTitle = implode(' ', $titleWords);

    // Fallback to a default title if the resulting title is too short or empty
    return $chatTitle ?: 'General Conversation';
}

//     public function updateChat(Request $request)
// {
//     // Validate the input
//     $request->validate([
//         'chat_id' => 'required|integer|exists:chats,id', // Validate chat_id is required, an integer, and exists in the chats table
//         'query' => 'required|string',
//     ]);

//     // Retrieve the chat ID from the request
//     $chatId = $request->input('chat_id');
//     $chat = Chat::findOrFail($chatId);

//     // Ensure the authenticated user owns the chat
//     if ($chat->user_id != auth()->id()) {
//         return response()->json(['error' => 'Unauthorized'], 403);
//     }

//     // Extract text from slides
//     $slidePath = storage_path('app/slides');
//     $allText = $this->extractSlidesText($slidePath);

//     // Get the AI-generated response
//     $query = $request->input('query');
//     $response = $this->getNlpResponse($query, $allText);

//     // Create a new conversation in the chat
//     $conversation = Conversation::create([
//         'chat_id' => $chat->id,
//         'query' => $query,
//         'response' => $response,
//     ]);

//     return response()->json([
//         'chat_id' => $chat->id,
//         'conversation' => $conversation
//     ]);
// }


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
                    $pages = $pdf->getPages();
                    if (!empty($pages)) {
                        foreach ($pages as $idx => $page) {
                            $pageText = trim($page->getText());
                            if ($pageText !== '') {
                                $allText .= "[SOURCE: {$fileName} p." . ($idx + 1) . "] " . $pageText . "\n";
                            }
                        }
                    } else {
                        $allText .= "[SOURCE: {$fileName}] " . $pdf->getText() . "\n";
                    }
                    continue;
                }

                if ($ext === 'ppt' || $ext === 'pptx') {
                    $pptReader = PptParser::createReader('PowerPoint2007');
                    $presentation = $pptReader->load($filePath);
                    foreach ($presentation->getAllSlides() as $slideIndex => $slide) {
                        $slideText = '';
                        foreach ($slide->getShapeCollection() as $shape) {
                            if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                                $slideText .= ' ' . $shape->getPlainText();
                            }
                        }
                        $slideText = trim($slideText);
                        if ($slideText !== '') {
                            $allText .= "[SOURCE: {$fileName} slide " . ($slideIndex + 1) . "] {$slideText}\n";
                        }
                    }
                    continue;
                }

                if ($ext === 'docx' || $ext === 'xlsx') {
                    $allText .= "[SOURCE: {$fileName}] " . $this->extractFromOpenXml($filePath, $ext) . "\n";
                    continue;
                }

                if ($ext === 'txt' || $ext === 'csv') {
                    $allText .= "[SOURCE: {$fileName}] " . file_get_contents($filePath) . "\n";
                    continue;
                }

                if ($ext === 'doc' || $ext === 'xls') {
                    $allText .= "[SOURCE: {$fileName}] " . $this->extractFromLegacyBinary($filePath) . "\n";
                }
            } catch (\Throwable $e) {
                Log::warning('Skipping unreadable document during extraction.', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                    'userId' => auth()->id(),
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
