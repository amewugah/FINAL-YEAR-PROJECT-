// src/stores/index.js

import { store } from 'quasar/wrappers';
import { createPinia, defineStore } from 'pinia';
import { api } from 'src/boot/axios';

export const useChatStore = defineStore('chat', {
  state: () => ({
    chats: [], // Array to hold chat data
    groups: [], // Array to hold group data
    user: null, // Store user details
    token: null, // Store JWT token
  }),
  actions: {
    clearSessionData() {
      this.chats = [];
      this.groups = [];
      this.user = null;
      this.token = null;
    },
    async fetchChats() {
      try {
        const response = await api.get('/ai/chats');
        this.chats = response.data;
        console.log(response.data);
      } catch (error) {
        console.error('Failed to fetch chats', error);
      }
    },
    async fetchGroups() {
      try {
        const response = await api.get('/getgroups');
        this.groups = response.data;
        console.log(response.data);
      } catch (error) {
        console.error('Failed to fetch groups', error);
      }
    },
    async login(credentials) {
      try {
        const response = await api.post('/login', credentials);

        // Extract token and user details
        const token = response.data.access_token || response.data.token;
        const user = response.data.user;
        if (!token) {
          throw new Error('Token missing from login response');
        }

        // Save details in state
        this.token = token;
        this.user = user;

        // Save in localStorage
        localStorage.setItem('jwt_token', token);
        localStorage.setItem('username', user?.name || '');
        localStorage.setItem('user_id', String(user?.id || ''));
        return true; // Login success
      } catch (error) {
        console.error('Login failed:', error);
        throw new Error('Login failed'); // Return error for UI feedback
      }
    },
  },
});

export default store(() => {
  const pinia = createPinia();
  return pinia;
});
