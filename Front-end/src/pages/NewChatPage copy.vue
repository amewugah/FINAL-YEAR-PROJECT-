<template>
  <q-page class="chat-page full-screen">
    <q-layout view="hHh lpR fFf">
      <q-page-container>
        <div class="chat-container q-pa-md">
          <div class="chat-box q-pa-md">
            <!-- Message History -->
            <div class="messages">
              <div v-for="(msg, index) in messages" :key="index" class="message">
                <q-card v-if="msg.sender === 'user'" class="user-message">
                  <q-card-section>{{ msg.text }}</q-card-section>
                </q-card>
                <q-card v-else class="response-message">
                  <q-card-section>{{ msg.text }}</q-card-section>
                </q-card>
              </div>

              <!-- Typing Indicator -->
              <div v-if="botTyping" class="typing-indicator">
                <q-spinner-dots size="24px" color="primary" />
                <span class="typing-text">Bot is typing...</span>
              </div>
            </div>

            <!-- Chat Input -->
            <div class="chat-input">
              <q-input
                filled
                v-model="message"
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

<script>
import axios from 'axios';

export default {
  data() {
    return {
      messages: [{ sender: 'bot', text: 'Hello! How can I assist you today?' }],
      message: '',
      botTyping: false, // Tracks if the bot is typing
      chatid: '',
      botresponse:'',
    };
  },
  methods: {
    async sendMessage() {
    if (this.message.trim()) {
      // Add the user message to the chat
      this.messages.push({ sender: 'user', text: this.message });

      // Set the bot as typing
      this.botTyping = true;

      const token = localStorage.getItem('jwt_token'); // Get the JWT token from localStorage

      try {
        // Simulate sending a message to the backend using the token
        const response = await axios.post(
          'http://localhost:8000/api/ai/chat/createchatorupdateconvo',
          { query: this.message, new_chat: true },
          {
            headers: {
              Authorization: `Bearer ${token}`, // Include the token in the Authorization header
            },
          }
        );

        // Clear the message input field
        this.message = '';
        this.chatid = response.data.chat_id;
        this.botresponse = response.data.message;
        console.log(response.data)

        // Display the bot's response character by character
        this.displayTypingEffect(response.data.response);
      } catch (error) {
        console.error('Error sending message:', error);
      }
    }
  },

  displayTypingEffect(responseText) {
    // Initialize an empty message for the bot
    let currentText = '';
    let index = 0;

    const typingInterval = setInterval(() => {
      if (index < responseText.length) {
        // Append the next character to the current text
        currentText += responseText[index];
        index++;

        // Update the message being displayed with the new character
        if (this.messages[this.messages.length - 1].sender === 'bot') {
          this.messages[this.messages.length - 1].text = currentText;
        } else {
          // Add a new message for the bot if one doesn't exist yet
          this.messages.push({ sender: 'bot', text: currentText });
        }
      } else {
        // Stop the typing effect when the whole response is displayed
        clearInterval(typingInterval);
        this.botTyping = false;
      }
    }, 50); // 50 ms delay between characters
  },
  },
};
</script>

<style scoped>
.full-screen {
  height: 100vh;
}

.chat-page {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  background-color: #f7f7f7;
}

.chat-container {
  max-width: 800px;
  width: 100%;
  height: 80vh;
  display: flex;
  flex-direction: column;
}

.chat-box {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  background: #ffffff;
  border-radius: 8px;
  height: 100%;
  padding: 1rem;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.messages {
  flex-grow: 1;
  overflow-y: auto;
  margin-bottom: 16px;
}

.message {
  margin-bottom: 10px;
}

.user-message {
  background-color: #d1e7dd;
  margin-left: auto;
  max-width: 75%;
}

.response-message {
  background-color: #e2e3e5;
  max-width: 75%;
}

.typing-indicator {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.typing-text {
  margin-left: 8px;
  font-size: 14px;
  color: #888;
}

.chat-input {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-top: 1px solid #ddd;
}
</style>
