<template>
  <q-page class="chat-page full-screen">
    <q-layout view="hHh lpR fFf">
      <q-page-container>
        <div class="chat-container q-pa-md">
          <div class="chat-box q-pa-md">
            <!-- Message History -->
            <div class="messages" ref="messageContainer">
              <div v-for="msg in chatMessages" :key="msg.id" class="message">
                <!-- User message -->
                <q-card class="user-message" v-if="msg.query">
                  <q-card-section>
                    <strong>{{ msg.user_name }}:</strong> {{ msg.query }}
                  </q-card-section>
                </q-card>
                <!-- Bot response -->
                <q-card class="response-message" v-if="msg.response">
                  <q-card-section>
                    <strong>Bot:</strong> {{ msg.response }}
                  </q-card-section>
                </q-card>
              </div>
            </div>

            <!-- Typing Indicator -->
            <div v-if="botTyping" class="typing-indicator">
              <q-spinner-dots size="24px" color="primary" />
              <span class="typing-text">Bot is typing...</span>
            </div>

            <!-- Chat Input -->
            <div class="chat-input">
              <q-input
                filled
                v-model="userMessage"
                placeholder="Type a message..."
                @keyup.enter="sendMessage"
                autofocus
                :disable="botTyping"
              />
              <q-btn
                color="primary"
                icon="send"
                @click="sendMessage"
                :disable="botTyping"
              />
            </div>
          </div>
        </div>
      </q-page-container>
    </q-layout>
  </q-page>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { api } from 'src/boot/axios';
import Pusher from 'pusher-js';

// Enable Pusher logging for debugging (development only)
Pusher.logToConsole = true;

// Reactive variables
const currentChat = ref(null);
const userMessage = ref('');
const chatMessages = ref([]);
const botTyping = ref(false);
const route = useRoute();
const messageContainer = ref(null);

// Initialize Pusher instance
const pusher = new Pusher('15c8098ecb1a6a3e562e', {
  cluster: 'mt1',
  encrypted: true,
});

function scrollToBottom() {
  nextTick(() => {
    if (messageContainer.value) {
      messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
    }
  });
}

watch(() => route.params.groupId, (newGroupId) => {
  if (newGroupId) {
    loadChat(newGroupId);
    setupPusher(newGroupId);
  }
});

async function loadChat(groupId) {
  try {
    const response = await api.get(`/groups/conversations/${groupId}`, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('jwt_token')}`,
      },
    });

    currentChat.value = response.data;

    chatMessages.value = (currentChat.value || []).map(conv => ({
      id: conv.id,
      user_name : conv.user_name,
      query: conv.query || '',
      response: conv.response || '',
    }));

    scrollToBottom();
  } catch (error) {
    console.error('Error loading chat:', error);
  }
}

function setupPusher(groupId) {
  // Unsubscribe from any previous channel if it exists
  const existingChannel = pusher.channels.channels[`group-chat-${groupId}`];
  if (existingChannel) {
    pusher.unsubscribe(`group-chat-${groupId}`);
  }

  // Subscribe to the new group channel
  const channel = pusher.subscribe(`group-chat-${groupId}`);

  // Bind to the 'new-message' event on the channel
  channel.bind('new-message', (data) => {
    const newMessage = {
      id: data.conversation.id,  // Use the message ID from the Pusher response
      query: data.conversation.query || '',  // User message
      response: data.conversation.response || '',  // Bot response
      user_name: data.conversation.user_name
    };

    // Push the new message into chatMessages
    chatMessages.value.push(newMessage);
    scrollToBottom();
  });
}

async function sendMessage() {
  if (!userMessage.value) return;

  const groupId = route.params.groupId;

  const userMsg = {
    id: Date.now(),
    query: userMessage.value,
    response: '',
    user_name : localStorage.getItem('username'),
  };

  chatMessages.value.push(userMsg);
  botTyping.value = true;
  const userText = userMessage.value;
  userMessage.value = '';
  scrollToBottom();

  try {
    const response = await api.post(`/groups/chat/${groupId}`, {
      query: userText,
    }, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('jwt_token')}`,
      },
    });

    const botResponse = response.data.conversation.response;

    // Adding delay to simulate bot typing
    setTimeout(() => {
      chatMessages.value.push({
        id: Date.now() + 1,
        query: '',
        response: botResponse,
      });
      botTyping.value = false;
      scrollToBottom();
    }, 500); // Adjust delay as needed
  } catch (error) {
    console.error('Error sending message:', error);
    botTyping.value = false;
  }
}

onMounted(() => {
  const groupId = route.params.groupId;
  if (groupId) {
    loadChat(groupId);
    setupPusher(groupId);
  }
});
</script>

<style scoped>
.chat-window {
  max-height: 500px;
  overflow-y: auto;
  padding: 16px;
  background-color: #f0f0f0;
}

.user-message {
  background-color: #d1e7dd;
  padding: 8px;
  border-radius: 10px;
  margin-bottom: 8px;
}

.response-message {
  background-color: #f8d7da;
  padding: 8px;
  border-radius: 10px;
  margin-bottom: 8px;
}

.typing-indicator {
  display: flex;
  align-items: center;
  margin-top: 10px;
}

.typing-text {
  margin-left: 10px;
  font-size: 14px;
}
</style>
