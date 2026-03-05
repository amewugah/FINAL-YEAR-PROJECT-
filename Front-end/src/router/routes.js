const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '/login', component: () => import('pages/IndexPage.vue') },
      { path: '/chat/new-chat', name: 'newchat', component: () => import('pages/NewChatPage.vue') },
      {
        path: '/chat/:chatId',
        name: 'chat', // Add a name to the route
        component: () => import('pages/ChatPage.vue'),
        props: true, // Pass route parameters as props to the component
      },
      {
        path: '/groups/:groupId',
        name: 'groups', // Add a name to the route
        component: () => import('pages/GroupPage.vue'),
        props: true, // Pass route parameters as props to the component
      },

    ],
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue'),
  },
];

export default routes;
