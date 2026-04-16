<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-toolbar-title>Study Chat</q-toolbar-title>
        <q-btn
          v-if="isAuthenticated"
          flat
          dense
          icon="group_add"
          label="Create Group"
          @click="showCreateGroupDialog = true"
        />
        <q-btn
          v-if="isAuthenticated"
          flat
          dense
          icon="vpn_key"
          label="Join Group"
          @click="showJoinGroupDialog = true"
        />
        <q-btn v-if="isAuthenticated" flat dense icon="account_circle">
          <q-menu>
            <q-list style="min-width: 180px">
              <q-item clickable v-close-popup @click="showProfileDialog = true">
                <q-item-section avatar><q-icon name="edit" /></q-item-section>
                <q-item-section>Edit Profile</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="logout">
                <q-item-section avatar><q-icon name="logout" /></q-item-section>
                <q-item-section>Logout</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above bordered>
      <q-list>
        <!-- Create New Chat Button -->
        <q-item>
          <q-btn
            color="primary"
            icon="add"
            label="Create New Chat"
            class="q-ma-md"
            @click="createNewChat"
          />
        </q-item>

        <!-- Chat History: Groups -->
        <q-item-label header>Groups</q-item-label>
        <q-item
          v-for="group in groupchats"
          :key="group.id"
          clickable
          @click="openGroupConversation(group)"
        >
          <q-item-section avatar>
            <q-icon name="chat" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ group.name }}</q-item-label>
          </q-item-section>
        </q-item>

        <!-- Chat History: Today -->
        <q-item-label header>Today</q-item-label>
        <q-item
          v-for="chat in todayChats"
          :key="chat.id"
          clickable
          @click="openChat(chat)"
        >
          <q-item-section avatar>
            <q-icon name="chat" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ chat.chat_title }}</q-item-label>
          </q-item-section>
        </q-item>

        <!-- Chat History: Yesterday -->
        <q-item-label header>Yesterday</q-item-label>
        <q-item
          v-for="chat in yesterdayChats"
          :key="chat.id"
          clickable
          @click="openChat(chat)"
        >
          <q-item-section avatar>
            <q-icon name="chat" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ chat.chat_title }}</q-item-label>
          </q-item-section>
        </q-item>

        <!-- Chat History: Last 7 Days -->
        <q-item-label header>Last 7 Days</q-item-label>
        <q-item
          v-for="chat in last7DaysChats"
          :key="chat.id"
          clickable
          @click="openChat(chat)"
        >
          <q-item-section avatar>
            <q-icon name="chat" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ chat.chat_title }}</q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <!-- Page Content -->
    <q-page-container>
      <router-view />
    </q-page-container>

    <q-dialog v-model="showCreateGroupDialog">
      <q-card style="min-width: 420px">
        <q-card-section><div class="text-h6">Create Group</div></q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="newGroup.name" label="Group name" filled />
          <q-input v-model="newGroup.description" label="Description" filled type="textarea" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Create" :loading="creatingGroup" @click="createGroup" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showProfileDialog">
      <q-card style="min-width: 420px">
        <q-card-section><div class="text-h6">Edit Profile</div></q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="profileForm.phone" label="Phone" filled />
          <q-input v-model="profileForm.bio" label="Bio" filled type="textarea" />
          <q-input v-model="profileForm.socialLinksRaw" label="Social links (comma separated)" filled />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Save" :loading="savingProfile" @click="saveProfile" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showJoinGroupDialog">
      <q-card style="min-width: 420px">
        <q-card-section><div class="text-h6">Join Group</div></q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input
            v-model="joinGroupCode"
            label="Invite code"
            filled
            hint="Ask the group owner for the invite code."
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Join" :loading="joiningGroup" @click="joinGroupByCode" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useChatStore } from 'src/stores/index'; // Import the chat store
import { Notify } from 'quasar';
import { api } from 'src/boot/axios';

const router = useRouter(); // Get the router instance
const chatStore = useChatStore(); // Get the chat store instance

const leftDrawerOpen = ref(false);
const isAuthenticated = computed(() => {
  return !!chatStore.token || !!localStorage.getItem('jwt_token');
});
const showCreateGroupDialog = ref(false)
const creatingGroup = ref(false)
const newGroup = ref({
  name: '',
  description: '',
})
const showProfileDialog = ref(false)
const savingProfile = ref(false)
const showJoinGroupDialog = ref(false)
const joiningGroup = ref(false)
const joinGroupCode = ref('')
const profileForm = ref({
  phone: '',
  bio: '',
  socialLinksRaw: '',
})

// Fetch chats and groups on component mount
async function fetchData() {
  const token = localStorage.getItem('jwt_token');
  if (!token) {
    chatStore.clearSessionData();
    Notify.create({
      message: 'Please login to continue.',
      color: 'warning',
      icon: 'login',
    });
    router.push('/login'); // Redirect to login if no token
    return;
  }

  try {
    chatStore.token = token;
    await Promise.all([chatStore.fetchChats(), chatStore.fetchGroups()]); // Fetch both chats and groups
  } catch (error) {
    chatStore.clearSessionData();
    Notify.create({
      message: 'Could not load chats/groups. Please login again.',
      color: 'negative',
      icon: 'error',
    });
    router.push('/login');
    console.error('Failed to fetch data', error);

  }
}
const groupchats = computed(() => chatStore.groups);


// Get today's, yesterday's, and last 7 days' chats
const todayChats = computed(() => {
  const today = new Date().setHours(0, 0, 0, 0);
  return chatStore.chats.filter(chat => new Date(chat.created_at) >= today);
});

const yesterdayChats = computed(() => {
  const today = new Date().setHours(0, 0, 0, 0);
  const yesterday = new Date(today).setDate(new Date(today).getDate() - 1);
  return chatStore.chats.filter(chat => new Date(chat.created_at) < today && new Date(chat.created_at) >= yesterday);
});

const last7DaysChats = computed(() => {
  const today = new Date().setHours(0, 0, 0, 0);
  const sevenDaysAgo = new Date(today).setDate(new Date(today).getDate() - 7);
  return chatStore.chats.filter(chat => new Date(chat.created_at) < sevenDaysAgo);
});



// Fetch the data when the component mounts
onMounted(fetchData);

// Toggle Drawer
function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value;
}

// Create New Chat Action
function createNewChat() {
  router.push({ name: 'newchat' });
}

// Open an existing chat
function openChat(chat) {
  router.push({ name: 'chat', params: { chatId: chat.id } });
}

// Open a group conversation
function openGroupConversation(group) { // Fixed reference to group
  router.push({ name: 'groups', params: { groupId: group.id } });
}

async function createGroup() {
  if (!newGroup.value.name?.trim()) {
    Notify.create({ message: 'Group name is required.', color: 'warning', icon: 'warning' })
    return
  }
  creatingGroup.value = true
  try {
    const response = await api.post('/create/groups', {
      name: newGroup.value.name,
      description: newGroup.value.description,
    })
    Notify.create({ message: response.data?.message || 'Group created.', color: 'positive', icon: 'check_circle' })
    if (response.data?.invite_code) {
      Notify.create({
        message: `Invite code: ${response.data.invite_code}`,
        color: 'info',
        icon: 'vpn_key',
        timeout: 8000,
      })
    }
    showCreateGroupDialog.value = false
    newGroup.value = { name: '', description: '' }
    await chatStore.fetchGroups()
    const groupId = response.data?.group?.id
    if (groupId) {
      router.push({ name: 'groups', params: { groupId } })
    }
  } catch (error) {
    Notify.create({ message: error.response?.data?.message || 'Failed to create group.', color: 'negative', icon: 'error' })
  } finally {
    creatingGroup.value = false
  }
}

async function joinGroupByCode() {
  const code = joinGroupCode.value?.trim()
  if (!code) {
    Notify.create({ message: 'Invite code is required.', color: 'warning', icon: 'warning' })
    return
  }

  joiningGroup.value = true
  try {
    const response = await api.post('/groups/join-by-code', {
      invite_code: code,
    })
    Notify.create({ message: response.data?.message || 'Joined group successfully.', color: 'positive', icon: 'check_circle' })
    showJoinGroupDialog.value = false
    joinGroupCode.value = ''
    await chatStore.fetchGroups()
    const groupId = response.data?.group?.id
    if (groupId) {
      router.push({ name: 'groups', params: { groupId } })
    }
  } catch (error) {
    Notify.create({ message: error.response?.data?.message || 'Failed to join group.', color: 'negative', icon: 'error' })
  } finally {
    joiningGroup.value = false
  }
}

async function saveProfile() {
  savingProfile.value = true
  try {
    const links = profileForm.value.socialLinksRaw
      ? profileForm.value.socialLinksRaw.split(',').map((v) => v.trim()).filter(Boolean)
      : []
    const payload = {
      phone: profileForm.value.phone || null,
      bio: profileForm.value.bio || null,
      social_links: links,
    }
    const response = await api.patch('/profile/update-field', payload)
    Notify.create({ message: response.data?.message || 'Profile updated.', color: 'positive', icon: 'check_circle' })
    showProfileDialog.value = false
  } catch (error) {
    Notify.create({ message: error.response?.data?.message || 'Failed to update profile.', color: 'negative', icon: 'error' })
  } finally {
    savingProfile.value = false
  }
}

async function logout() {
  try {
    await api.post('/logout')
  } catch (error) {
    // Continue local logout even if backend token invalid.
  } finally {
    chatStore.clearSessionData();
    localStorage.removeItem('jwt_token')
    localStorage.removeItem('username')
    localStorage.removeItem('user_id')
    Notify.create({ message: 'Logged out successfully.', color: 'positive', icon: 'check_circle' })
    router.push('/login')
  }
}
</script>

<style scoped>
.q-drawer {
  max-width: 300px;
}

.q-item-label {
  padding: 0 16px;
}
</style>
