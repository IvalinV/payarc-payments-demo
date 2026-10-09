import './bootstrap';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import App from './portal/App';
import '../css/app.css';

const root = document.querySelector('#app');
if (!root) throw new Error('App root element was not found');
createRoot(root).render(createElement(App));
