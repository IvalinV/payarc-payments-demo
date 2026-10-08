import './bootstrap';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import App from './portal/App';
import '../css/app.css';

createRoot(document.querySelector('#app')).render(createElement(App));
