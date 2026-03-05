<template>
  <q-layout view="lHh Lpr lFf">
    <!-- Header, Drawer, and other elements as before -->

    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      bordered
    >
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

        <!-- Chat History: Today -->
        <q-item-label header>Today</q-item-label>
        <q-item v-for="chat in todayChats" :key="chat.id" clickable @click="openChat(chat)">
          <q-item-section avatar>
            <q-icon name="chat" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ chat.title }}</q-item-label>
            <q-item-label caption>{{ chat.lastMessageTime }}</q-item-label>
          </q-item-section>
        </q-item>

       <!-- Chat History: Yesterday -->
       <q-item-label header>Yesterday</q-item-label>
        <q-item v-for="chat in yesterdayChats" :key="chat.id" clickable @click="openChat(chat)">
          <q-item-section avatar>
            <q-icon name="chat" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ chat.title }}</q-item-label>
            <q-item-label caption>{{ chat.lastMessageTime }}</q-item-label>
          </q-item-section>
        </q-item>

        <!-- Chat History: Last 7 Days -->
        <q-item-label header>Last 7 Days</q-item-label>
        <q-item v-for="chat in last7DaysChats" :key="chat.id" clickable @click="openChat(chat)">
          <q-item-section avatar>
            <q-icon name="chat" />
          </q-item-section>
          <q-item-section>
            <q-item-label>{{ chat.title }}</q-item-label>
            <q-item-label caption>{{ chat.lastMessageTime }}</q-item-label>
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
import { ref, onMounted } from 'vue'
import api from 'src/boot/axios'  // Import the axios instance

const todayChats = ref([])
const yesterdayChats = ref([])
const last7DaysChats = ref([])
const leftDrawerOpen = ref(false)

// Fetch chats on component mount
async function fetchChats() {
  try {
    const response = await api.get('/ai/chats')  // Your API endpoint for fetching chats
    const chats = response.data  // Assuming the API returns a list of chats

    // Example logic for splitting chats into "Today", "Yesterday", and "Last 7 Days"
    const today = new Date().setHours(0, 0, 0, 0)
    const yesterday = new Date(today).setDate(new Date(today).getDate() - 1)

    todayChats.value = chats.filter(chat => new Date(chat.lastMessageTime) >= today)
    yesterdayChats.value = chats.filter(chat => new Date(chat.lastMessageTime) < today && new Date(chat.lastMessageTime) >= yesterday)
    last7DaysChats.value = chats.filter(chat => new Date(chat.lastMessageTime) < yesterday)
  } catch (error) {
    console.error('Failed to fetch chats', error)
  }
}

// Fetch the chats when the component mounts
onMounted(() => {
  fetchChats();
  const token = localStorage.getItem('token')

if (!token) {
  // Redirect to the login page if no token is found
  router.push('/login')
} else {
  fetchChats()  // Fetch chat data if the user is authenticated
}
})

// Toggle Drawer
function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

// Create New Chat Action
function createNewChat() {
  console.log('Creating a new chat...')
}

// Open an existing chat
function openChat(chat) {
  console.log(`Opening chat: ${chat.title}`)
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
