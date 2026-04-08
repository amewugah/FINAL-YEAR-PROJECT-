<template>
  <q-page class="chat-page full-screen">
    <q-layout view="hHh lpR fFf">
      <q-page-container>
        <div class="chat-container q-pa-md">
          <div class="chat-box q-pa-md">
            <div class="uploaded-slides q-mb-sm">
              <div class="uploaded-slides-title">Uploaded slides</div>
              <div v-if="uploadedSlides.length === 0" class="uploaded-slides-empty">
                No slides uploaded yet.
              </div>
              <q-list v-else dense bordered class="uploaded-slides-list">
                <q-item v-for="slide in uploadedSlides" :key="slide.id">
                  <q-item-section>
                    <q-item-label>{{ slide.file_name }}</q-item-label>
                    <q-item-label caption>{{ slide.file_path }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-btn
                      flat
                      round
                      dense
                      color="negative"
                      icon="delete"
                      @click.stop="deleteSlide(slide.id, slide.file_name)"
                    />
                  </q-item-section>
                </q-item>
              </q-list>
            </div>

            <div class="messages" ref="messageContainer">
              <div v-for="(msg, index) in chatMessages" :key="index" class="message">
                <q-card class="user-message" v-if="msg.sender1 === 'user'">
                  <q-card-section>{{ msg.query }}</q-card-section>
                </q-card>
                <q-card class="response-message" v-if="msg.sender2 === 'bot'">
                  <q-card-section>{{ msg.response }}</q-card-section>
                </q-card>
              </div>
            </div>

            <div v-if="botTyping" class="typing-indicator">
              <q-spinner-dots size="24px" color="primary" />
              <span class="typing-text">Bot is typing...</span>
            </div>

            <div class="chat-input">
              <q-file
                :key="fileInputKey"
                v-model="selectedSlide"
                filled
                dense
                clearable
                accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.txt,.csv"
                label="Upload slide"
                class="slide-upload"
                :disable="botTyping || uploadingSlide"
              />
              <q-btn
                color="secondary"
                icon="upload_file"
                label="Upload"
                @click="uploadSlide"
                :loading="uploadingSlide"
                :disable="botTyping || uploadingSlide || !selectedSlide"
              />
              <q-input
                filled
                v-model="userMessage"
                placeholder="Type a message..."
                @keyup.enter="sendMessage"
                autofocus
                :disable="botTyping || uploadingSlide"
              />
              <q-btn color="primary" icon="send" @click="sendMessage" :disable="botTyping || uploadingSlide" />
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
import { Notify } from 'quasar'
import { api } from 'src/boot/axios'

const currentChat = ref(null)
const userMessage = ref('')
const chatMessages = ref([])
const botTyping = ref(false)
const selectedSlide = ref(null)
const uploadingSlide = ref(false)
const uploadedSlides = ref([])
const fileInputKey = ref(0)
const route = useRoute()
const messageContainer = ref(null)

function scrollToBottom() {
  nextTick(() => {
    if (messageContainer.value) {
      messageContainer.value.scrollTop = messageContainer.value.scrollHeight
    }
  })
}

watch(() => route.params.chatId, (newChatId) => {
  if (newChatId) loadChat(newChatId)
})

async function loadChat(chatId) {
  try {
    const response = await api.get(`/ai/chats/${chatId}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
    })

    currentChat.value = response.data
    chatMessages.value = (currentChat.value.conversations || []).map((conv) => ({
      query: conv.query,
      sender1: 'user',
      response: conv.response,
      sender2: 'bot',
    }))
    scrollToBottom()
  } catch (error) {
    console.error('Error loading chat:', error)
    Notify.create({
      message: 'Failed to load chat history.',
      color: 'negative',
      icon: 'error',
    })
  }
}

async function sendMessage() {
  if (!userMessage.value) return

  const chatId = route.params.chatId
  chatMessages.value.push({ sender1: 'user', query: userMessage.value })

  botTyping.value = true
  const userText = userMessage.value
  userMessage.value = ''
  scrollToBottom()

  try {
    const response = await api.put('/ai/chat/update', {
      chat_id: chatId,
      query: userText,
    }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
    })

    if (response.data?.conversation?.response) {
      chatMessages.value.push({
        sender2: 'bot',
        response: response.data.conversation.response,
      })
    } else if (response.data?.message) {
      chatMessages.value.push({
        sender2: 'bot',
        response: response.data.message,
      })
    } else {
      chatMessages.value.push({
        sender2: 'bot',
        response: 'I could not process that request. Please try again.',
      })
    }

    botTyping.value = false
    scrollToBottom()
  } catch (error) {
    console.error('Error sending message:', error)
    botTyping.value = false
    Notify.create({
      message: 'Failed to send message. Please try again.',
      color: 'negative',
    })
  }
}

async function uploadSlide() {
  if (!selectedSlide.value) {
    Notify.create({
      message: 'Select a document first (pdf, ppt, pptx, doc, docx, xls, xlsx, txt, csv).',
      color: 'warning',
    })
    return
  }

  uploadingSlide.value = true
  try {
    const formData = new FormData()
    formData.append('slide', selectedSlide.value)

    const response = await api.post('/ai/upload-slide', formData, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('jwt_token')}`,
        'Content-Type': 'multipart/form-data',
      },
    })

    const uploadedPath = response.data?.file_path || ''
    const uploadedName = uploadedPath ? uploadedPath.split('/').pop() : selectedSlide.value?.name
    if (uploadedPath) {
      uploadedSlides.value = [
        {
          id: `temp-${Date.now()}`,
          file_name: uploadedName || 'Uploaded document',
          file_path: uploadedPath,
        },
        ...uploadedSlides.value,
      ]
    }

    Notify.create({
      message: response.data?.message || 'Slide uploaded successfully.',
      color: 'positive',
      icon: 'check_circle',
      position: 'top-right',
      timeout: 2500,
      closeBtn: true,
    })
    chatMessages.value.push({
      sender2: 'bot',
      response: 'Slide uploaded. You can now ask questions about it.',
    })
    selectedSlide.value = null
    fileInputKey.value += 1
    await fetchUploadedSlides()
    scrollToBottom()
  } catch (error) {
    console.error('Error uploading slide:', error)
    Notify.create({
      message: error.response?.data?.message || 'Slide upload failed.',
      color: 'negative',
      icon: 'error',
      position: 'top-right',
      timeout: 3000,
      closeBtn: true,
    })
  } finally {
    uploadingSlide.value = false
  }
}

async function fetchUploadedSlides() {
  try {
    const response = await api.get('/ai/slides', {
      params: { t: Date.now() },
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
    })
    uploadedSlides.value = response.data?.slides || []
  } catch (error) {
    console.error('Error loading slides:', error)
    Notify.create({
      message: 'Failed to load uploaded documents list.',
      color: 'warning',
      icon: 'warning',
    })
  }
}

async function deleteSlide(slideId, fileName = 'this document') {
  if (!window.confirm(`Delete "${fileName}"?`)) return
  try {
    const response = await api.delete(`/ai/slides/${slideId}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
    })
    uploadedSlides.value = uploadedSlides.value.filter((slide) => slide.id !== slideId)
    Notify.create({
      message: response.data?.message || 'Document deleted successfully.',
      color: 'positive',
      icon: 'check_circle',
      position: 'top-right',
      timeout: 2500,
      closeBtn: true,
    })
    await fetchUploadedSlides()
  } catch (error) {
    Notify.create({
      message: error.response?.data?.message || 'Failed to delete document.',
      color: 'negative',
      icon: 'error',
      position: 'top-right',
      timeout: 3000,
      closeBtn: true,
    })
  }
}

onMounted(() => {
  const chatId = route.params.chatId
  if (chatId) loadChat(chatId)
  fetchUploadedSlides()
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

.chat-input {
  display: grid;
  grid-template-columns: minmax(180px, 260px) auto 1fr auto;
  gap: 8px;
  align-items: center;
}

.slide-upload {
  min-width: 180px;
}

.uploaded-slides {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 8px;
  background: #fafafa;
}

.uploaded-slides-title {
  font-weight: 600;
  margin-bottom: 6px;
}

.uploaded-slides-empty {
  color: #666;
  font-size: 13px;
}

.uploaded-slides-list {
  max-height: 140px;
  overflow-y: auto;
  background: #fff;
}
</style>
