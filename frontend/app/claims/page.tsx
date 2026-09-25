"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { getClaims, getToken, type Claim } from "../lib/api";

const statusLabels: Record<string, string> = {
    received: "Diterima",
    processing_by_vendor: "Diproses vendor",
    completed: "Selesai",
    rejected: "Ditolak",
};

const statusOptions = ["all", "received", "processing_by_vendor", "completed", "rejected"];

export default function ClaimsPage() {
    const router = useRouter();
    const [claims, setClaims] = useState<Claim[]>([]);
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState("all");
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        if (!getToken()) {
            router.replace("/login");
            return;
        }

        getClaims()
            .then(setClaims)
            .catch((requestError: unknown) => {
                setError(requestError instanceof Error ? requestError.message : "Data klaim tidak dapat dimuat.");
            })
            .finally(() => setIsLoading(false));
    }, [router]);

    const filteredClaims = useMemo(() => {
        const normalizedQuery = query.toLowerCase().trim();

        return claims.filter((claim) => {
            const matchesStatus = status === "all" || claim.status === status;
            const searchable = [claim.claim_code, claim.serial_number, claim.customer?.name, claim.product?.name]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            return matchesStatus && (!normalizedQuery || searchable.includes(normalizedQuery));
        });
    }, [claims, query, status]);

    const countByStatus = (claimStatus: string) =>
        claims.filter((claim) => claimStatus === "all" || claim.status === claimStatus).length;

    return (
        <main className="claims-page">
            <header className="page-header">
                <Link className="back-link" href="/">← Dashboard</Link>
                <div className="page-header-content">
                    <div>
                        <p className="dashboard-kicker">OPERASIONAL</p>
                        <h1>Klaim garansi</h1>
                        <p>Kelola keluhan pelanggan dan pantau progres penanganannya.</p>
                    </div>
                    <button className="primary-action" type="button" onClick={() => setIsFormOpen(true)}>+ Catat klaim</button>
                </div>
            </header>

            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                <div className="claim-overview">
                    <div><span>Total klaim</span><strong>{claims.length}</strong></div>
                    <div><span>Menunggu tindakan</span><strong>{countByStatus("received")}</strong></div>
                    <div><span>Diproses vendor</span><strong>{countByStatus("processing_by_vendor")}</strong></div>
                    <div><span>Selesai</span><strong>{countByStatus("completed")}</strong></div>
                </div>

                <section className="claims-list-panel">
                    <div className="claims-list-toolbar">
                        <div>
                            <h2>Semua klaim</h2>
                            <span>{filteredClaims.length} data ditemukan</span>
                        </div>
                        <div className="claim-filters">
                            <label className="search-field"><span>⌕</span><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari kode, pelanggan, produk..." /></label>
                            <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Filter status klaim">
                                {statusOptions.map((option) => <option key={option} value={option}>{option === "all" ? "Semua status" : statusLabels[option]}</option>)}
                            </select>
                        </div>
                    </div>

                    <div className="claims-table-wrap">
                        {isLoading ? <div className="empty-state"><strong>Memuat data klaim...</strong></div> : <table className="claims-table claims-page-table">
                            <thead><tr><th>Klaim</th><th>Pelanggan</th><th>Produk / serial</th><th>Tanggal</th><th>Status</th><th /></tr></thead>
                            <tbody>{filteredClaims.map((claim: Claim) => (
                                <tr key={claim.id}>
                                    <td><strong>{claim.claim_code}</strong><small>ID #{claim.id.toString().padStart(3, "0")}</small></td>
                                    <td>{claim.customer?.name || "-"}</td>
                                    <td><strong>{claim.product?.name || "-"}</strong><small>{claim.serial_number || "Tanpa serial number"}</small></td>
                                    <td>{new Date(claim.claim_date).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })}</td>
                                    <td><span className={`status-badge status-${claim.status}`}>{statusLabels[claim.status] || claim.status}</span></td>
                                    <td><button className="table-action" type="button">Detail →</button></td>
                                </tr>
                            ))}</tbody>
                        </table>}
                        {!isLoading && filteredClaims.length === 0 && <div className="empty-state"><strong>Klaim tidak ditemukan</strong><span>Coba ubah kata kunci atau filter status.</span></div>}
                    </div>
                </section>
            </section>

            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={() => setIsFormOpen(false)}>
                <section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="claim-modal-title" onClick={(event) => event.stopPropagation()}>
                    <button className="modal-close" type="button" onClick={() => setIsFormOpen(false)} aria-label="Tutup">×</button>
                    <p className="dashboard-kicker">KLAIM BARU</p>
                    <h2 id="claim-modal-title">Catat klaim garansi</h2>
                    <p className="modal-description">Lengkapi detail klaim untuk diproses oleh tim.</p>
                    <label>Nomor serial<input placeholder="Contoh: GT-AX91-001" /></label>
                    <label>Deskripsi kerusakan<textarea placeholder="Jelaskan keluhan pelanggan..." rows={4} /></label>
                    <div className="modal-actions"><button className="secondary-action" type="button" onClick={() => setIsFormOpen(false)}>Batal</button><button className="primary-action" type="button" onClick={() => setIsFormOpen(false)}>Simpan klaim</button></div>
                </section>
            </div>}
        </main>
    );
}