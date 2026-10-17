import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App.jsx';
import './styles/main.scss';

// =============================================================================
// APPLICATION MOUNT
// =============================================================================

// Disable router transitions to avoid pending navigation leaving the previous view visible.

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter useTransitions={false}>
      <App />
    </BrowserRouter>
  </React.StrictMode>,
);
