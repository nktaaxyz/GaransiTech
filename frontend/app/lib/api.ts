export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";
const TOKEN_KEY = "garansitech_token";

export type ApiUser = {
  id: number;
  name: string;
  email: string;
};

export type Claim = {
  id: number;
  claim_code: string;
  serial_number: string | null;
  claim_date: string;
  status: string;
  customer?: { name: string };
  product?: { name: string };
  vendor?: { name: string };
};

type PaginatedClaims = {
  data: Claim[];
};

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken();
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);
  if (options.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`${API_URL}${path}`, { ...options, headers });
  const data = (await response.json().catch(() => ({}))) as {
    message?: string;
    errors?: Record<string, string[]>;
  } & T;

  if (!response.ok) {
    const validationMessage = data.errors
      ? Object.values(data.errors).flat()[0]
      : undefined;
    throw new Error(validationMessage || data.message || "Permintaan gagal.");
  }

  return data;
}

export function getCurrentUser(): Promise<ApiUser> {
  return request<ApiUser>("/user");
}

export async function getClaims(): Promise<Claim[]> {
  const response = await request<PaginatedClaims>("/claims");
  return response.data;
}

export function logout(): Promise<{ message: string }> {
  return request<{ message: string }>("/auth/logout", { method: "POST" });
}
