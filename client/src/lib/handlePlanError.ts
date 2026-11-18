import { toast } from "sonner";

export function handlePlanError(error: any): boolean {
  if (error?.data?.code === "FORBIDDEN" && error?.message) {
    toast.error(error.message, {
      action: { label: "Ver planos", onClick: () => { window.location.href = "/pricing"; } },
      duration: 8000,
    });
    return true;
  }
  return false;
}
