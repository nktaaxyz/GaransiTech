"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import {
  ApiUser,
  Claim,
  clearToken,
  getClaims,
  getCurrentUser,
  getToken,
  logout,
} from "../lib/api";

const statusLabels: Record<string, string> = {
  received: "Diterima",
  forwaded_to_vendor: "Diteruskan ke vendor",
  processing_by_vendor: "Diproses vendor",
  completed: "Selesai",
  rejected: "Ditolak",
};

export default function Dashboard() {
  const router = useRouter();
  const [user, setUser] = useState<ApiUser | null>(null);
  const [claims, setClaims] = useState<Claim[]>([]);
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!getToken()) {
      router.replace("/login");
      return;
    }

    Promise.all([getCurrentUser(), getClaims()])
      .then(([currentUser, currentClaims]) => {
        setUser(currentUser);
        setClaims(currentClaims);
      })
      .catch((requestError: unknown) => {
        clearToken();
        setError(
          requestError instanceof Error
            ? requestError.message
            : "Sesi tidak dapat diverifikasi.",
        );
        router.replace("/login");
      })
      .finally(() => setIsLoading(false));
  }, [router]);

  async function handleLogout() {
    try {
      await logout();
    } catch {
      // Token is cleared locally even if the server is unavailable.
    } finally {
      clearToken();
      router.replace("/login");
    }
  }

  if (isLoading) {
    return <main className="dashboard-state">Memuat dashboard...</main>;
  }

  return (
    <main className="dashboard-page">
      <header className="dashboard-header">
        <div>
          <div className="dashboard-brand"><span>G</span> GaransiTech</div>
          <p>Dashboard manajemen garansi</p>
        </div>
        <div className="dashboard-account">
          <div><strong>{user?.name}</strong><small>{user?.email}</small></div>
          <button type="button" onClick={handleLogout}>Keluar</button>
        </div>
      </header>

      <section className="dashboard-content">
        <div className="dashboard-intro">
          <div><p className="dashboard-kicker">RINGKASAN</p><h1>Selamat datang, {user?.name}</h1></div>
          <div className="claim-count"><strong>{claims.length}</strong><span>Total klaim</span></div>
        </div>
        {error && <p className="form-error" role="alert">{error}</p>}
        <section className="claims-card">
          <div className="claims-heading"><h2>Daftar klaim garansi</h2><span>{claims.length} klaim</span></div>
          {claims.length === 0 ? (
            <div className="empty-state">Belum ada klaim garansi.</div>
          ) : (
            <div className="claims-table-wrap">
              <table className="claims-table">
                <thead><tr><th>Kode klaim</th><th>Produk</th><th>Pelanggan</th><th>Tanggal</th><th>Status</th></tr></thead>
                <tbody>{claims.map((claim) => (
                  <tr key={claim.id}>
                    <td><strong>{claim.claim_code}</strong><small>{claim.serial_number || "Tanpa serial number"}</small></td>
                    <td>{claim.product?.name || "-"}</td>
                    <td>{claim.customer?.name || "-"}</td>
                    <td>{new Date(claim.claim_date).toLocaleDateString("id-ID")}</td>
                    <td><span className={`status-badge status-${claim.status}`}>{statusLabels[claim.status] || claim.status}</span></td>
                  </tr>
                ))}</tbody>
              </table>
            </div>
          )}
        </section>
      </section>
    </main>
  );
}
