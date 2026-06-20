import { initialStore } from "./data";

const KEY = "elite-client-crm-store-v1";

export function loadStore() {
  try {
    const stored = window.localStorage.getItem(KEY);
    return stored ? { ...initialStore, ...JSON.parse(stored) } : initialStore;
  } catch {
    return initialStore;
  }
}

export function saveStore(store) {
  window.localStorage.setItem(KEY, JSON.stringify(store));
}

export function money(value) {
  return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 }).format(Number(value) || 0);
}

export const isoToday = () => new Date().toISOString().slice(0, 10);
export const dateLabel = (value) => new Date(`${value}T12:00:00`).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
export const initials = (name = "Client") => name.split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase();
