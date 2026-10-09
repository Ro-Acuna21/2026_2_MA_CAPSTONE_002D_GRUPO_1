import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { Toaster } from 'sonner'
import App from './App'
import { ClinicProvider } from './state/clinic-store'
import './styles/globals.css'

createRoot(document.getElementById('root')!).render(<StrictMode><BrowserRouter><ClinicProvider><App /><Toaster richColors position="top-right" /></ClinicProvider></BrowserRouter></StrictMode>)
