<template>
  <q-page class="signup-page">
    <q-layout view="hHh lpR fFf">
      <q-page-container>
        <q-card class="row q-pa-md q-gutter-md" flat>
          <div class="col-6 flex flex-center q-pa-md">
            <q-img src="https://via.placeholder.com/400" alt="Illustration" />
          </div>

          <div class="col-6 q-pa-md">
            <q-form @submit.prevent="handleSignup" class="q-gutter-md">
              <q-input filled v-model="name" label="Full name" />
              <q-input filled v-model="email" label="Email" type="email" />
              <q-input filled v-model="password" label="Password" type="password" />
              <q-input filled v-model="passwordConfirmation" label="Confirm password" type="password" />

              <q-btn label="Sign up" color="primary" @click="handleSignup" :loading="loading" />
              <q-btn flat label="Back to login" color="secondary" @click="$router.push('/login')" />
            </q-form>
          </div>
        </q-card>
      </q-page-container>
    </q-layout>
  </q-page>
</template>

<script>
import { Notify } from 'quasar';
import { api } from 'src/boot/axios';

export default {
  data() {
    return {
      name: '',
      email: '',
      password: '',
      passwordConfirmation: '',
      loading: false,
    };
  },
  methods: {
    async handleSignup() {
      if (!this.name || !this.email || !this.password || !this.passwordConfirmation) {
        Notify.create({
          message: 'Please fill in all fields.',
          color: 'warning',
          icon: 'warning',
        });
        return;
      }

      this.loading = true;
      try {
        const response = await api.post('/register', {
          name: this.name,
          email: this.email,
          password: this.password,
          password_confirmation: this.passwordConfirmation,
        });

        Notify.create({
          message: response.data?.message || 'Signup successful. Please login.',
          color: 'positive',
          icon: 'check_circle',
        });

        this.$router.push('/login');
      } catch (error) {
        Notify.create({
          message: error.response?.data?.message || 'Signup failed. Please try again.',
          color: 'negative',
          icon: 'error',
        });
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.signup-page {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
}
</style>
