import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'
import { ApplicationErrorBoundary } from './ErrorSystem.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <ApplicationErrorBoundary>
      <App />
    </ApplicationErrorBoundary>
  </StrictMode>,
)
