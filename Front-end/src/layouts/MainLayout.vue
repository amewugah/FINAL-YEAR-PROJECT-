<template>
  <q-layout view="lHh Lpr lFf">
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
  </q-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useChatStore } from 'src/stores/index'; // Import the chat store

const router = useRouter(); // Get the router instance
const chatStore = useChatStore(); // Get the chat store instance

const leftDrawerOpen = ref(false);

// Fetch chats and groups on component mount
async function fetchData() {
  const token = localStorage.getItem('jwt_token');
  if (!token) {
    router.push('/login'); // Redirect to login if no token
    return;
  }

  try {
    await Promise.all([chatStore.fetchChats(), chatStore.fetchGroups()]); // Fetch both chats and groups
  } catch (error) {
    this.router.push('/login');
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
</script>

<style scoped>
.q-drawer {
  max-width: 300px;
}

.q-item-label {
  padding: 0 16px;
}
</style>
