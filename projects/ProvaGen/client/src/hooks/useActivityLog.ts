import { useState, useEffect } from "react";

export interface Activity {
  id: string;
  type: "navigation" | "action";
  label: string;
  path: string;
  timestamp: number;
}

const STORAGE_KEY = "prof-aid-activity-log";

export function useActivityLog() {
  const [activities, setActivities] = useState<Activity[]>([]);

  useEffect(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      try {
        setActivities(JSON.parse(saved));
      } catch (e) {
        console.error("Failed to parse activity log", e);
      }
    }
  }, []);

  const logActivity = (label: string, path: string, type: "navigation" | "action" = "navigation") => {
    const newActivity: Activity = {
      id: `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`,
      type,
      label,
      path,
      timestamp: Date.now(),
    };

    setActivities((prev) => {
      // Filter out duplicate paths within the last 15 seconds to avoid clutter
      const filtered = prev.filter(
        (a) => a.path !== path || Date.now() - a.timestamp > 15000
      );
      const updated = [newActivity, ...filtered].slice(0, 8); // Keep last 8 actions
      localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
      return updated;
    });
  };

  const clearLog = () => {
    localStorage.removeItem(STORAGE_KEY);
    setActivities([]);
  };

  return { activities, logActivity, clearLog };
}
