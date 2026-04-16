<template>
  <q-page class="chat-page full-screen">
    <q-layout view="hHh lpR fFf">
      <q-page-container>
        <div class="chat-container q-pa-md">
          <div class="chat-box q-pa-md">
            <div class="q-mb-sm row items-center justify-between q-gutter-sm">
              <q-file
                v-model="groupSlideFile"
                filled
                dense
                clearable
                accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.txt,.csv"
                label="Upload group document"
                class="col-grow"
                :disable="uploadingGroupSlide"
              />
              <q-btn
                color="secondary"
                icon="upload_file"
                label="Upload"
                :loading="uploadingGroupSlide"
                :disable="uploadingGroupSlide || !groupSlideFile"
                @click="uploadGroupSlide"
              />
              <q-btn
                color="secondary"
                icon="person_add"
                label="Add User"
                @click="showAddUserDialog = true"
              />
            </div>
            <q-card flat bordered class="q-mb-sm">
              <q-card-section class="text-subtitle2">Group Slides</q-card-section>
              <q-separator />
              <q-list dense>
                <q-item v-if="groupSlides.length === 0">
                  <q-item-section>
                    <q-item-label caption>No group documents uploaded yet.</q-item-label>
                  </q-item-section>
                </q-item>
                <q-item v-for="slide in groupSlides" :key="slide.file_path">
                  <q-item-section>
                    <q-item-label>{{ slide.file_name }}</q-item-label>
                    <q-item-label caption>{{ slide.file_path }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-btn
                      v-if="isGroupOwner"
                      flat
                      dense
                      round
                      color="negative"
                      icon="delete"
                      @click="deleteGroupSlide(slide)"
                    />
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card>
            <q-card flat bordered class="q-mb-sm">
              <q-card-section class="row items-center justify-between">
                <div class="text-subtitle2">Group Invite Code</div>
                <q-btn
                  flat
                  dense
                  icon="content_copy"
                  label="Copy"
                  :disable="!groupInviteCode"
                  @click="copyInviteCode"
                />
              </q-card-section>
              <q-separator />
              <q-card-section>
                <div v-if="groupInviteCode" class="text-body1">{{ groupInviteCode }}</div>
                <div v-else class="text-caption text-grey">Invite code unavailable.</div>
              </q-card-section>
            </q-card>
            <q-card flat bordered class="q-mb-sm">
              <q-card-section class="text-subtitle2">Group Members</q-card-section>
              <q-separator />
              <q-list dense>
                <q-item v-for="member in groupMembers" :key="member.id">
                  <q-item-section>
                    <q-item-label>
                      {{ member.name }}
                      <q-badge v-if="Number(member.id) === Number(groupOwnerId)" color="primary" class="q-ml-sm">Owner</q-badge>
                    </q-item-label>
                    <q-item-label caption>{{ member.email }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-btn
                      v-if="member.id !== currentUserId"
                      flat
                      dense
                      round
                      color="negative"
                      icon="person_remove"
                      @click="removeGroupMember(member)"
                    />
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card>
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
              <q-card v-if="pendingMessage" class="user-message pending-message">
                <q-card-section>
                  <strong>{{ pendingMessage.user_name }}:</strong> {{ pendingMessage.query }}
                  <span class="pending-status"> (sending...)</span>
                </q-card-section>
              </q-card>
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

    <q-dialog v-model="showAddUserDialog">
      <q-card style="min-width: 380px">
        <q-card-section>
          <div class="text-h6">Add User to Group</div>
        </q-card-section>
        <q-card-section>
          <q-input
            filled
            v-model="newUserEmail"
            type="email"
            label="User email"
            placeholder="johndoe@example.com"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn color="primary" label="Add" :loading="addingUser" @click="addUserToGroup" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { api } from 'src/boot/axios';
import Pusher from 'pusher-js';
import { Notify } from 'quasar';

// Enable Pusher logging for debugging (development only)
Pusher.logToConsole = true;

// Reactive variables
const currentChat = ref(null);
const userMessage = ref('');
const chatMessages = ref([]);
const botTyping = ref(false);
const route = useRoute();
const messageContainer = ref(null);
const showAddUserDialog = ref(false);
const newUserEmail = ref('');
const addingUser = ref(false);
const groupMembers = ref([]);
const groupSlideFile = ref(null);
const uploadingGroupSlide = ref(false);
const currentUserId = Number(localStorage.getItem('user_id') || 0);
const groupSlides = ref([]);
const groupOwnerId = ref(null);
const isGroupOwner = ref(false);
const pendingMessage = ref(null);
const groupInviteCode = ref('');

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
    Notify.create({
      message: 'Failed to load group chat.',
      color: 'negative',
      icon: 'error',
    });
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
    if (
      pendingMessage.value &&
      pendingMessage.value.query?.trim() === (newMessage.query || '').trim() &&
      pendingMessage.value.user_name === newMessage.user_name
    ) {
      pendingMessage.value = null;
    }
    scrollToBottom();
  });
}

async function sendMessage() {
  if (!userMessage.value) return;

  const groupId = route.params.groupId;
  const isAiCommand = /^\/ask(ai)?\s*:?\s*/i.test(userMessage.value.trim());
  pendingMessage.value = {
    id: `pending-${Date.now()}`,
    query: userMessage.value,
    user_name: localStorage.getItem('username'),
  };
  botTyping.value = isAiCommand;
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

    // New messages are rendered from realtime broadcasts (Pusher),
    // so avoid local optimistic pushes that cause duplicates.
    botTyping.value = false;
  } catch (error) {
    console.error('Error sending message:', error);
    botTyping.value = false;
    pendingMessage.value = null;
    Notify.create({
      message: error.response?.data?.message || 'Failed to send group message.',
      color: 'negative',
      icon: 'error',
    });
  }
}

async function addUserToGroup() {
  if (!newUserEmail.value?.trim()) {
    Notify.create({
      message: 'Please enter an email address.',
      color: 'warning',
      icon: 'warning',
    });
    return;
  }

  addingUser.value = true;
  try {
    const groupId = route.params.groupId;
    const response = await api.post(`/groups/${groupId}/users`, {
      email: newUserEmail.value.trim(),
    }, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('jwt_token')}`,
      },
    });

    Notify.create({
      message: response.data?.message || 'User added to group successfully.',
      color: 'positive',
      icon: 'check_circle',
    });
    newUserEmail.value = '';
    showAddUserDialog.value = false;
    await fetchGroupMembers();
  } catch (error) {
    Notify.create({
      message: error.response?.data?.message || 'Failed to add user to group.',
      color: 'negative',
      icon: 'error',
    });
  } finally {
    addingUser.value = false;
  }
}

async function fetchGroupMembers() {
  try {
    const groupId = route.params.groupId;
    const response = await api.get(`/groups/${groupId}/members`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
    });
    groupMembers.value = response.data?.members || [];
    groupOwnerId.value = Number(response.data?.owner_id || 0);
    groupInviteCode.value = response.data?.invite_code || '';
    isGroupOwner.value = groupOwnerId.value === currentUserId;
  } catch (error) {
    Notify.create({
      message: 'Failed to load group members.',
      color: 'negative',
      icon: 'error',
    });
  }
}

async function copyInviteCode() {
  if (!groupInviteCode.value) return;
  try {
    await navigator.clipboard.writeText(groupInviteCode.value);
    Notify.create({
      message: 'Invite code copied.',
      color: 'positive',
      icon: 'check_circle',
    });
  } catch (error) {
    Notify.create({
      message: 'Could not copy invite code. Copy it manually.',
      color: 'warning',
      icon: 'warning',
    });
  }
}

async function removeGroupMember(member) {
  if (!window.confirm(`Remove ${member.name} from this group?`)) return;
  try {
    const groupId = route.params.groupId;
    const response = await api.delete(`/groups/${groupId}/users/${member.id}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
    });
    Notify.create({
      message: response.data?.message || 'Member removed successfully.',
      color: 'positive',
      icon: 'check_circle',
    });
    groupMembers.value = groupMembers.value.filter((m) => m.id !== member.id);
  } catch (error) {
    Notify.create({
      message: error.response?.data?.message || 'Failed to remove member.',
      color: 'negative',
      icon: 'error',
    });
  }
}

async function uploadGroupSlide() {
  if (!groupSlideFile.value) return;
  uploadingGroupSlide.value = true;
  try {
    const groupId = route.params.groupId;
    const formData = new FormData();
    formData.append('slides', groupSlideFile.value);
    const response = await api.post(`/groups/slides/${groupId}`, formData, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('jwt_token')}`,
        'Content-Type': 'multipart/form-data',
      },
    });
    Notify.create({
      message: response.data?.message || 'Group document uploaded successfully.',
      color: 'positive',
      icon: 'check_circle',
    });
    groupSlideFile.value = null;
    await fetchGroupSlides();
  } catch (error) {
    Notify.create({
      message: error.response?.data?.message || 'Failed to upload group document.',
      color: 'negative',
      icon: 'error',
    });
  } finally {
    uploadingGroupSlide.value = false;
  }
}

async function fetchGroupSlides() {
  try {
    const groupId = route.params.groupId;
    const response = await api.get(`/groups/slides/${groupId}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
    });
    groupSlides.value = response.data?.slides || [];
    const ownerId = Number(response.data?.owner_id || groupOwnerId.value || 0);
    groupOwnerId.value = ownerId;
    isGroupOwner.value = ownerId === currentUserId;
  } catch (error) {
    Notify.create({
      message: 'Failed to load group slides.',
      color: 'negative',
      icon: 'error',
    });
  }
}

async function deleteGroupSlide(slide) {
  if (!window.confirm(`Delete "${slide.file_name}" from group?`)) return;
  try {
    const groupId = route.params.groupId;
    const response = await api.delete(`/groups/slides/${groupId}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('jwt_token')}` },
      data: { file_name: slide.file_name },
    });
    Notify.create({
      message: response.data?.message || 'Group slide deleted successfully.',
      color: 'positive',
      icon: 'check_circle',
    });
    groupSlides.value = groupSlides.value.filter((item) => item.file_path !== slide.file_path);
  } catch (error) {
    Notify.create({
      message: error.response?.data?.message || 'Failed to delete group slide.',
      color: 'negative',
      icon: 'error',
    });
  }
}

onMounted(() => {
  const groupId = route.params.groupId;
  if (groupId) {
    loadChat(groupId);
    setupPusher(groupId);
    fetchGroupMembers();
    fetchGroupSlides();
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

.pending-message {
  opacity: 0.75;
}

.pending-status {
  font-style: italic;
  color: #666;
}
</style>
