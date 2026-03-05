<template>
  <q-page class="login-page">
    <q-layout view="hHh lpR fFf">
      <q-page-container>
        <q-card class="row q-pa-md q-gutter-md" flat>
          <!-- Left Column: Illustration -->
          <div class="col-6 flex flex-center q-pa-md">
            <q-img src="https://via.placeholder.com/400" alt="Illustration" />
          </div>

          <!-- Right Column: Login Form -->
          <div class="col-6 q-pa-md">
            <q-form @submit="handleSubmit" class="q-gutter-md">
              <q-input filled v-model="email" label="Email" />
              <q-input filled v-model="password" type="password" label="Password" />

              <q-btn label="Login" color="primary" @click="handleSubmit" :loading="loading" />

              <q-spinner v-if="loading" />
            </q-form>

            <q-notify
              v-if="error"
              position="top-right"
              color="negative"
              message="Login failed. Please check your credentials."
              icon="warning"
            />
          </div>
        </q-card>
      </q-page-container>
    </q-layout>
  </q-page>
</template>

<script>
import { useChatStore } from 'src/stores/index';

export default {
  data() {
    return {
      email: '',
      password: '',
      loading: false,
      error: false,
    };
  },
  methods: {
    async handleSubmit() {
      this.loading = true;
      this.error = false;

      const chatStore = useChatStore();

      try {
        await chatStore.login({ email: this.email, password: this.password });

        // Redirect to the chat page after successful login
        this.$router.push('/chat/new-chat');
      } catch (err) {
        this.error = true;
        console.error('Login error:', err.message);
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.login-page {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
}
</style>
