import { trpc } from "@/lib/trpc";
import { TRPCClientError } from "@trpc/client";
import { useCallback, useEffect, useMemo } from "react";

type UseAuthOptions = {
  redirectOnUnauthenticated?: boolean;
  redirectPath?: string;
};

export function useAuth(options?: UseAuthOptions) {
  const { redirectOnUnauthenticated = false } = options ?? {};
  const utils = trpc.useUtils();

  const meQuery = trpc.auth.me.useQuery(undefined, {
    // Retry network errors up to 2 times (not auth 401 errors)
    retry: (failureCount, error) => {
      if ((error as any)?.data?.httpStatus === 401) return false;
      return failureCount < 2;
    },
    retryDelay: 1500,
    refetchOnWindowFocus: false,
    staleTime: 5 * 60 * 1000, // 5 min — avoid excessive refetches
  });

  const logoutMutation = trpc.auth.logout.useMutation({
    onSuccess: () => {
      utils.auth.me.setData(undefined, null);
    },
  });

  const logout = useCallback(async () => {
    try {
      await logoutMutation.mutateAsync();
    } catch (error: unknown) {
      if (
        error instanceof TRPCClientError &&
        error.data?.code === "UNAUTHORIZED"
      ) {
        return;
      }
      throw error;
    } finally {
      utils.auth.me.setData(undefined, null);
      await utils.auth.me.invalidate();
    }
  }, [logoutMutation, utils]);

  const state = useMemo(() => {
    return {
      user: meQuery.data ?? null,
      loading: meQuery.isLoading || logoutMutation.isPending,
      error: meQuery.error ?? logoutMutation.error ?? null,
      isAuthenticated: Boolean(meQuery.data),
    };
  }, [
    meQuery.data,
    meQuery.error,
    meQuery.isLoading,
    logoutMutation.error,
    logoutMutation.isPending,
  ]);

  // redirectOnUnauthenticated is kept for API compatibility but
  // auth redirection is now handled by AuthGuard/AuthModalContext
  useEffect(() => {
    if (!redirectOnUnauthenticated) return;
    if (meQuery.isLoading || logoutMutation.isPending) return;
    if (state.user) return;
    if (meQuery.error) return; // never redirect on network errors
  }, [redirectOnUnauthenticated, logoutMutation.isPending, meQuery.isLoading, meQuery.error, state.user]);

  return {
    ...state,
    refresh: () => meQuery.refetch(),
    logout,
  };
}
