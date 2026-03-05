<template>
  <q-page class="chat-page full-screen">
    <q-layout view="hHh lpR fFf">
      <q-page-container>
        <div class="chat-container q-pa-md">
          <div class="chat-box q-pa-md">

            <!-- Message History when chat conversation loads -->
            <div class="messages" ref="messageContainer">
              <!-- Load initial chat messages from the API -->
              <div v-for="(msg, index) in chatMessages" :key="index" class="message">
                <!-- User message -->
                <q-card class="user-message" v-if="msg.sender1 === 'user'">
                  <q-card-section>{{ msg.query }}</q-card-section>
                </q-card>

                <!-- Bot response -->
                <q-card class="response-message" v-if="msg.sender2 === 'bot'">
                  <q-card-section>{{ msg.response }}</q-card-section>
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
import { ref, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { api } from 'src/boot/axios'

// Reactive variables
const currentChat = ref(null)
const userMessage = ref('')
const chatMessages = ref([])
const botTyping = ref(false) // Indicator for bot typing
const route = useRoute()

// Reference for message container (to scroll down)
const messageContainer = ref(null)

// Scroll to the bottom of the chat after new messages are added
function scrollToBottom() {
  nextTick(() => {
    if (messageContainer.value) {
      messageContainer.value.scrollTop = messageContainer.value.scrollHeight
    }
  })
}

// Watch for chatId changes and load the chat accordingly
watch(() => route.params.chatId, (newChatId) => {
  if (newChatId) {
    loadChat(newChatId)
  }
})

// Function to load a specific chat and show the history
async function loadChat(chatId) {
  try {
  const response = await api.get(`/ai/chats/${chatId}`, {
    headers: {
      Authorization: `Bearer ${localStorage.getItem('jwt_token')}`,
    },
  })

  currentChat.value = response.data

  // Load conversation history and set sender types based on API response
  chatMessages.value = (currentChat.value.conversations || []).map(conv => ({
    query: conv.query,
    sender1: 'user', // User query
    response: conv.response,
    sender2: 'bot' // Bot response
  }))

  scrollToBottom() // Scroll to the bottom when chat is loaded

} catch (error) {
  console.error('Error loading chat:', error)
}

}

// Send new message and update chat with new conversations
async function sendMessage() {
  if (!userMessage.value) return

  const chatId = route.params.chatId // Get the current chat ID

  // Add user's message immediately to chatMessages
  chatMessages.value.push({
    sender1: 'user',
    query: userMessage.value, // Assuming the message field is 'query'
  })

  botTyping.value = true // Set bot typing to true
  const userText = userMessage.value
  userMessage.value = '' // Clear the input box
  scrollToBottom() // Scroll to bottom after user message

  try {
    // Simulate typing delay
    setTimeout(async () => {
      const response = await api.put('/ai/chat/update', {
        chat_id: chatId,
        query: userText,
      }, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('jwt_token')}`,
        },
      })

      const botResponse = response.data.conversation.response

      // Add bot's response to chatMessages after typing
      chatMessages.value.push({
        sender2: 'bot',
        response: botResponse, // Assuming the message field is 'response'
      })

      botTyping.value = false // Bot finished typing
      scrollToBottom() // Scroll to bottom after bot message
    }, 500) // Adjust delay to mimic bot's response time
  } catch (error) {
    console.error('Error sending message:', error)
    botTyping.value = false
  }
}

// Call loadChat when the component is mounted
onMounted(() => {
  const chatId = route.params.chatId
  if (chatId) {
    loadChat(chatId)
  }
})
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
