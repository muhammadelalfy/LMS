export type OfflineMutation = { id: string; path: string; method: string; body?: string; createdAt: number };

type DashboardSnapshot = {
  students: unknown[];
  worksheets: unknown[];
  attendance: unknown[];
  exams: unknown[];
  payments: unknown[];
  savedAt: number;
};

const SNAPSHOT_KEY = "al-imtiaz-dashboard-snapshot";
const QUEUE_KEY = "al-imtiaz-offline-mutations";

export function cacheDashboardSnapshot(snapshot: Omit<DashboardSnapshot, "savedAt">): void {
  window.localStorage.setItem(SNAPSHOT_KEY, JSON.stringify({ ...snapshot, savedAt: Date.now() }));
}

export function readDashboardSnapshot(): DashboardSnapshot | null {
  try {
    const value = window.localStorage.getItem(SNAPSHOT_KEY);
    return value ? JSON.parse(value) as DashboardSnapshot : null;
  } catch {
    return null;
  }
}

export function enqueueMutation(mutation: Omit<OfflineMutation, "id" | "createdAt">): void {
  const queue = readMutationQueue();
  queue.push({ ...mutation, id: crypto.randomUUID(), createdAt: Date.now() });
  window.localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
}

export function readMutationQueue(): OfflineMutation[] {
  try {
    const value = window.localStorage.getItem(QUEUE_KEY);
    return value ? JSON.parse(value) as OfflineMutation[] : [];
  } catch {
    return [];
  }
}

export function replaceMutationQueue(queue: OfflineMutation[]): void {
  window.localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
}
