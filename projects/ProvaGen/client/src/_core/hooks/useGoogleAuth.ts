// Frontend hook for Google OAuth login
import { useLocation } from "wouter";
import { useEffect } from "react";

export function useGoogleAuth() {
  const [, navigate] = useLocation();

  const initiateGoogleLogin = () => {
    // Redirect to Google OAuth endpoint
    window.location.href = "/api/auth/google";
  };

  const logout = async () => {
    try {
      await fetch("/api/auth/logout", { method: "POST" });
      navigate("/login");
    } catch (error) {
      console.error("Logout failed:", error);
    }
  };

  const checkSession = async () => {
    try {
      const response = await fetch("/api/auth/me");
      return response.ok;
    } catch {
      return false;
    }
  };

  return {
    initiateGoogleLogin,
    logout,
    checkSession,
  };
}
