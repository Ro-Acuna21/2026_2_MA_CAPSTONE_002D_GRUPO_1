import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) { return twMerge(clsx(inputs)) }
export const uid = (prefix: string) => `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`
export const toMinutes = (time: string) => { const [h, m] = time.split(':').map(Number); return h * 60 + m }
export const toTime = (minutes: number) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
export const dateFromToday = (offset = 0) => { const d = new Date(); d.setDate(d.getDate() + offset); return d.toLocaleDateString('en-CA', { timeZone: 'America/Santiago' }) }
export const nextBusinessDay = (offset: number) => { let date = dateFromToday(offset); while ([0, 6].includes(new Date(`${date}T12:00`).getDay())) date = dateFromToday(++offset); return date }
export const formatDateTime = (date: string, time: string) => new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(`${date}T${time}:00`))
