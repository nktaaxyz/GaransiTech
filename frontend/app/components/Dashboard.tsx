"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  ApiUser,
  Claim,
  DEMO_MODE,
  clearToken,
  demoClaims,
  demoUser,
  getClaims,
  getCurrentUser,
  getToken,
  logout,
} from "../lib/api";

const statusLabels: Record<string, string> = {
  received: "Diterima",
  forwarded_to_vendor: "Diteruskan ke vendor",
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

  const activeClaims = claims.filter(
    (claim) => !["completed", "rejected"].includes(claim.status),
  ).length;
  const completedClaims = claims.filter((claim) => claim.status === "completed").length;
  const rejectedClaims = claims.filter((claim) => claim.status === "rejected").length;
  const receivedClaims = claims.filter((claim) => claim.status === "received").length;
  const completionRate = claims.length
    ? Math.round((completedClaims / claims.length) * 100)
    : 0;

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
        if (DEMO_MODE) {
          setUser(demoUser);
          setClaims(demoClaims);
          return;
        }
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
      <aside className="dashboard-sidebar">
        <div className="dashboard-brand"><span>G</span> GaransiTech</div>
        <nav className="dashboard-nav" aria-label="Navigasi utama">
          <Link className="nav-item active" href="/"><span>01</span> Ringkasan</Link>
          <Link className="nav-item" href="/claims"><span>02</span> Klaim garansi</Link>
          <Link className="nav-item" href="/#activity"><span>03</span> Aktivitas</Link>
        </nav>
        <div className="sidebar-footer">
          <div className="account-avatar">{user?.name?.charAt(0).toUpperCase()}</div>
          <div><strong>{user?.name}</strong><small>{user?.email}</small></div>
          <button type="button" onClick={handleLogout} aria-label="Keluar dari akun">Keluar</button>
        </div>
      </aside>

      <section className="dashboard-main">
        <header className="dashboard-topbar">
          <div>
            <p className="dashboard-kicker">PUSAT KONTROL</p>
            <h1>Selamat datang, {user?.name}</h1>
            <p className="topbar-subtitle">Pantau garansi dan pastikan setiap klaim mendapat tindak lanjut.</p>
          </div>
          <div className="topbar-actions">
            <div className="topbar-date">{new Intl.DateTimeFormat("id-ID", { dateStyle: "long" }).format(new Date())}</div>
            <Link className="primary-action" href="/claims">+ Catat klaim</Link>
          </div>
        </header>
        <div className="dashboard-content" id="overview">
          {error && <p className="form-error" role="alert">{error}</p>}
          <section className="stats-grid" aria-label="Ringkasan klaim">
            <article className="stat-card stat-primary"><div className="stat-label">TOTAL KLAIM</div><strong>{claims.length}</strong><span className="stat-note">Semua klaim terdaftar</span></article>
            <article className="stat-card"><div className="stat-label">SEDANG DIPROSES</div><strong>{activeClaims}</strong><span className="stat-note positive">{receivedClaims} baru masuk</span></article>
            <article className="stat-card"><div className="stat-label">SELESAI</div><strong>{completedClaims}</strong><span className="stat-note positive">{completionRate}% dari total klaim</span></article>
            <article className="stat-card"><div className="stat-label">DITOLAK</div><strong>{rejectedClaims}</strong><span className="stat-note">Perlu perhatian</span></article>
          </section>

          <section className="workspace-strip" aria-label="Modul kerja">
            <div><span className="workspace-icon">G</span><div><strong>Garansi &amp; unit produk</strong><span>Kelola masa berlaku dan serial number</span></div></div>
            <div><span className="workspace-icon workspace-icon-warm">K</span><div><strong>Klaim masuk</strong><span>{activeClaims} klaim perlu ditindaklanjuti</span></div></div>
            <Link href="/claims">Buka daftar klaim <span>→</span></Link>
          </section>

          <section className="dashboard-grid">
            <article className="activity-card" id="activity">
              <div className="section-heading"><div><p className="dashboard-kicker">PERFORMA</p><h2>Alur klaim</h2></div><span className="period-label">Saat ini</span></div>
              <div className="progress-overview"><div><strong>{completionRate}%</strong><span>tingkat penyelesaian</span></div><div className="progress-ring" style={{ "--progress": `${completionRate * 3.6}deg` } as React.CSSProperties}><div>{completedClaims}/{claims.length || 0}</div></div></div>
              <div className="status-bars"><div><span>Diterima</span><b>{receivedClaims}</b><i><em style={{ width: `${claims.length ? (receivedClaims / claims.length) * 100 : 0}%` }} /></i></div><div><span>Selesai</span><b>{completedClaims}</b><i><em className="bar-success" style={{ width: `${completionRate}%` }} /></i></div><div><span>Ditolak</span><b>{rejectedClaims}</b><i><em className="bar-danger" style={{ width: `${claims.length ? (rejectedClaims / claims.length) * 100 : 0}%` }} /></i></div></div>
            </article>
            <article className="insight-card">
              <p className="dashboard-kicker">STATUS HARI INI</p><h2>Jaga ritme pelayanan</h2><p>{activeClaims ? `${activeClaims} klaim masih membutuhkan tindak lanjut.` : "Semua klaim sudah memiliki hasil akhir."}</p><div className="insight-line"><span>Target penyelesaian</span><strong>80%</strong></div><div className="target-track"><i style={{ width: `${Math.min(completionRate, 100)}%` }} /></div></article>
          </section>

          <section className="claims-card" id="claims">
            <div className="claims-heading"><div><p className="dashboard-kicker">TERBARU</p><h2>Daftar klaim garansi</h2></div><span>{claims.length} klaim</span></div>
            {claims.length === 0 ? (
              <div className="empty-state"><strong>Belum ada klaim</strong><span>Data klaim garansi akan muncul di sini.</span></div>
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
        </div>
      </section>
    </main>
  );
}
