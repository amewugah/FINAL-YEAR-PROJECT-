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
        const token = response.data.access_token;
        const user = response.data.user;

        // Save details in state
        this.token = token;
        this.user = user;
        console.log(this.token)

        // Save in localStorage
        localStorage.setItem('jwt_token', token);
        localStorage.setItem('username', user.name);
        localStorage.setItem('user_id', user.id);

        console.log('Login successful:', token);
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
