import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface UIState {
  locale: string
  sidebarOpen: boolean
  setLocale: (locale: string) => void
  toggleSidebar: () => void
}

export const useUIStore = create<UIState>()(
  persist(
    (set) => ({
      locale: 'pt-BR',
      sidebarOpen: true,
      setLocale: (locale) => {
        localStorage.setItem('plumora-locale', locale)
        set({ locale })
      },
      toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),
    }),
    { name: 'plumora-ui' },
  ),
)
