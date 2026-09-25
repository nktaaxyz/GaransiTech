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
  damage_description?: string;
  note?: string | null;
  forwarded_date?: string | null;
  vendor_reference_number?: string | null;
  resolution_date?: string | null;
  resolution_note?: string | null;
  status_logs?: ClaimStatusLog[];
  status: string;
  customer?: { name: string };
  product?: { name: string };
  vendor?: { name: string };
};

export type ClaimStatusLog = {
  id: number;
  old_status: string | null;
  new_status: string;
  note: string;
  created_at?: string;
};

export type ProductUnit = {
  id: number;
  serial_number: string;
  product?: { name: string };
  customer?: { name: string };
};

export type Product = {
  id: number;
  name: string;
  category: string | null;
  vendor?: Vendor;
  units_count?: number;
};

export type Vendor = {
  id: number;
  name: string;
  contact_person: string | null;
  phone: string | null;
  email: string | null;
  products_count?: number;
};

export type Customer = {
  id: number;
  name: string;
  phone: string;
  address: string | null;
  product_units_count?: number;
};

export type Warranty = {
  id: number;
  warranty_code: string;
  start_date: string;
  end_date: string;
  notes: string | null;
  status: "active" | "expiring" | "expired";
  product_unit: ProductUnit;
};

type PaginatedClaims = {
  data: Claim[];
};

type Paginated<T> = {
  data: T[];
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
    if (response.status === 401) clearToken();
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

export async function getWarranties(search = "", status = ""): Promise<Warranty[]> {
  const params = new URLSearchParams();
  if (search.trim()) params.set("search", search.trim());
  if (status) params.set("status", status);
  const suffix = params.toString() ? `?${params.toString()}` : "";
  const response = await request<Paginated<Warranty>>(`/warranties${suffix}`);
  return response.data;
}

export async function getProductUnits(): Promise<ProductUnit[]> {
  const response = await request<Paginated<ProductUnit>>("/product-units");
  return response.data;
}

export async function getProducts(): Promise<Product[]> {
  const response = await request<Paginated<Product>>("/products");
  return response.data;
}

export async function getVendors(): Promise<Vendor[]> {
  const response = await request<Paginated<Vendor>>("/vendors");
  return response.data;
}

export function createProduct(input: { name: string; category?: string; vendor_id: number }): Promise<Product> {
  return request<Product>("/products", { method: "POST", body: JSON.stringify(input) });
}

export function updateProduct(id: number, input: { name: string; category?: string; vendor_id: number }): Promise<Product> {
  return request<Product>(`/products/${id}`, { method: "PATCH", body: JSON.stringify(input) });
}

export function deleteProduct(id: number): Promise<{ message: string }> {
  return request<{ message: string }>(`/products/${id}`, { method: "DELETE" });
}

export function createVendor(input: { name: string; contact_person?: string; phone?: string; email?: string }): Promise<Vendor> {
  return request<Vendor>("/vendors", { method: "POST", body: JSON.stringify(input) });
}

export function updateVendor(id: number, input: { name: string; contact_person?: string; phone?: string; email?: string }): Promise<Vendor> {
  return request<Vendor>(`/vendors/${id}`, { method: "PATCH", body: JSON.stringify(input) });
}

export function deleteVendor(id: number): Promise<{ message: string }> {
  return request<{ message: string }>(`/vendors/${id}`, { method: "DELETE" });
}

export async function getCustomers(): Promise<Customer[]> {
  const response = await request<Paginated<Customer>>("/customers");
  return response.data;
}

export function createCustomer(input: {
  name: string;
  phone: string;
  address?: string;
}): Promise<Customer> {
  return request<Customer>("/customers", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function updateCustomer(id: number, input: { name: string; phone: string; address?: string }): Promise<Customer> {
  return request<Customer>(`/customers/${id}`, { method: "PATCH", body: JSON.stringify(input) });
}

export function deleteCustomer(id: number): Promise<{ message: string }> {
  return request<{ message: string }>(`/customers/${id}`, { method: "DELETE" });
}

export function createProductUnit(input: {
  product_id: number;
  customer_id: number;
  serial_number: string;
  purchase_date?: string;
}): Promise<ProductUnit> {
  return request<ProductUnit>("/product-units", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function updateProductUnit(id: number, input: {
  product_id: number;
  customer_id: number;
  serial_number: string;
  purchase_date?: string;
}): Promise<ProductUnit> {
  return request<ProductUnit>(`/product-units/${id}`, { method: "PATCH", body: JSON.stringify(input) });
}

export function deleteProductUnit(id: number): Promise<{ message: string }> {
  return request<{ message: string }>(`/product-units/${id}`, { method: "DELETE" });
}

export function createWarranty(input: {
  product_unit_id: number;
  start_date: string;
  end_date: string;
  notes?: string;
}): Promise<Warranty> {
  return request<Warranty>("/warranties", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function updateWarranty(id: number, input: {
  product_unit_id: number;
  start_date: string;
  end_date: string;
  notes?: string;
}): Promise<Warranty> {
  return request<Warranty>(`/warranties/${id}`, { method: "PATCH", body: JSON.stringify(input) });
}

export function deleteWarranty(id: number): Promise<{ message: string }> {
  return request<{ message: string }>(`/warranties/${id}`, { method: "DELETE" });
}

export function createClaim(input: {
  warranty_id: number;
  claim_date: string;
  damage_description: string;
  note?: string;
}): Promise<Claim> {
  return request<Claim>("/claims", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function updateClaim(id: number, input: { damage_description: string; claim_date: string }): Promise<Claim> {
  return request<Claim>(`/claims/${id}`, { method: "PATCH", body: JSON.stringify(input) });
}

export function updateClaimStatus(input: {
  claimId: number;
  status: string;
  note: string;
  forwarded_date?: string;
  vendor_reference_number?: string;
  resolution_date?: string;
  resolution_note?: string;
}): Promise<Claim> {
  const { claimId, ...body } = input;
  return request<Claim>(`/claims/${claimId}/status`, {
    method: "PATCH",
    body: JSON.stringify(body),
  });
}

export function logout(): Promise<{ message: string }> {
  return request<{ message: string }>("/auth/logout", { method: "POST" });
}
