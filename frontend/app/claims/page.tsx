"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createClaim, getClaims, getToken, getWarranties, updateClaimStatus, type Claim, type Warranty } from "../lib/api";

const statusLabels: Record<string, string> = {
    received: "Diterima",
    forwarded_to_vendor: "Diteruskan ke vendor",
    processing_by_vendor: "Diproses vendor",
    completed: "Selesai",
    rejected: "Ditolak",
};

const nextStatusOptions: Record<string, string[]> = {
    received: ["forwarded_to_vendor", "rejected"],
    forwarded_to_vendor: ["processing_by_vendor", "rejected"],
    processing_by_vendor: ["completed", "rejected"],
};

const statusOptions = ["all", "received", "processing_by_vendor", "completed", "rejected"];

export default function ClaimsPage() {
    const router = useRouter();
    const [claims, setClaims] = useState<Claim[]>([]);
    const [warranties, setWarranties] = useState<Warranty[]>([]);
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState("all");
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [selectedClaim, setSelectedClaim] = useState<Claim | null>(null);
    const [isStatusSaving, setIsStatusSaving] = useState(false);
    const [statusError, setStatusError] = useState("");
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        if (!getToken()) {
            router.replace("/login");
            return;
        }

        Promise.all([getClaims(), getWarranties("", "active")])
            .then(([loadedClaims, loadedWarranties]) => {
                setClaims(loadedClaims);
                setWarranties(loadedWarranties);
            })
            .catch((requestError: unknown) => {
                setError(requestError instanceof Error ? requestError.message : "Data klaim tidak dapat dimuat.");
            })
            .finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsSaving(true);
        setFormError("");
        const formData = new FormData(event.currentTarget);

        try {
            await createClaim({
                warranty_id: Number(formData.get("warranty_id")),
                claim_date: String(formData.get("claim_date")),
                damage_description: String(formData.get("damage_description")),
                note: String(formData.get("note") || ""),
            });
            setClaims(await getClaims());
            setIsFormOpen(false);
            event.currentTarget.reset();
        } catch (requestError) {
            setFormError(requestError instanceof Error ? requestError.message : "Klaim tidak dapat disimpan.");
        } finally {
            setIsSaving(false);
        }
    }

    async function handleStatusSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!selectedClaim) return;
        setIsStatusSaving(true);
        setStatusError("");
        const formData = new FormData(event.currentTarget);

        try {
            const updatedClaim = await updateClaimStatus({
                claimId: selectedClaim.id,
                status: String(formData.get("status")),
                note: String(formData.get("status_note")),
            });
            setClaims((currentClaims) => currentClaims.map((claim) => claim.id === updatedClaim.id ? updatedClaim : claim));
            setSelectedClaim(updatedClaim);
            event.currentTarget.reset();
        } catch (requestError) {
            setStatusError(requestError instanceof Error ? requestError.message : "Status klaim tidak dapat diperbarui.");
        } finally {
            setIsStatusSaving(false);
        }
    }

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
                                    <td><button className="table-action" type="button" onClick={() => setSelectedClaim(claim)}>Detail →</button></td>
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
                    {formError && <p className="form-error" role="alert">{formError}</p>}
                    <form onSubmit={handleSubmit}>
                        <label>Garansi / nomor serial<select name="warranty_id" required defaultValue=""><option value="" disabled>Pilih garansi aktif</option>{warranties.map((warranty) => <option key={warranty.id} value={warranty.id}>{warranty.product_unit.serial_number} — {warranty.product_unit.product?.name || "Produk"} / {warranty.product_unit.customer?.name || "Tanpa pelanggan"}</option>)}</select></label>
                        <label>Tanggal klaim<input name="claim_date" type="date" required defaultValue={new Date().toISOString().slice(0, 10)} /></label>
                        <label>Deskripsi kerusakan<textarea name="damage_description" placeholder="Jelaskan keluhan pelanggan..." rows={4} required /></label>
                        <label>Catatan awal<textarea name="note" placeholder="Catatan tambahan (opsional)" rows={3} /></label>
                        <div className="modal-actions"><button className="secondary-action" type="button" onClick={() => setIsFormOpen(false)}>Batal</button><button className="primary-action" type="submit" disabled={isSaving || warranties.length === 0}>{isSaving ? "Menyimpan..." : "Simpan klaim"}</button></div>
                    </form>
                </section>
            </div>}
            {selectedClaim && <div className="modal-backdrop" role="presentation" onClick={() => setSelectedClaim(null)}>
                <section className="claim-modal claim-detail-modal" role="dialog" aria-modal="true" aria-labelledby="claim-detail-title" onClick={(event) => event.stopPropagation()}>
                    <button className="modal-close" type="button" onClick={() => setSelectedClaim(null)} aria-label="Tutup">×</button>
                    <p className="dashboard-kicker">DETAIL KLAIM</p>
                    <h2 id="claim-detail-title">{selectedClaim.claim_code}</h2>
                    <span className={`status-badge status-${selectedClaim.status}`}>{statusLabels[selectedClaim.status] || selectedClaim.status}</span>
                    <div className="claim-detail-grid">
                        <div><small>Pelanggan</small><strong>{selectedClaim.customer?.name || "-"}</strong></div>
                        <div><small>Produk</small><strong>{selectedClaim.product?.name || "-"}</strong></div>
                        <div><small>Nomor serial</small><strong>{selectedClaim.serial_number || "-"}</strong></div>
                        <div><small>Tanggal klaim</small><strong>{new Date(selectedClaim.claim_date).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" })}</strong></div>
                    </div>
                    <div className="claim-detail-note"><small>Deskripsi kerusakan</small><p>{selectedClaim.damage_description || "Belum ada deskripsi kerusakan."}</p></div>
                    {selectedClaim.note && <div className="claim-detail-note"><small>Catatan</small><p>{selectedClaim.note}</p></div>}
                    {nextStatusOptions[selectedClaim.status] && <form className="status-update-form" onSubmit={handleStatusSubmit}>
                        <p className="dashboard-kicker">PERBARUI STATUS</p>
                        {statusError && <p className="form-error" role="alert">{statusError}</p>}
                        <label>Status berikutnya<select name="status" required defaultValue=""><option value="" disabled>Pilih status</option>{nextStatusOptions[selectedClaim.status].map((option) => <option key={option} value={option}>{statusLabels[option]}</option>)}</select></label>
                        <label>Catatan penanganan<textarea name="status_note" rows={3} required placeholder="Tuliskan tindakan atau hasil penanganan..." /></label>
                        <button className="primary-action" type="submit" disabled={isStatusSaving}>{isStatusSaving ? "Menyimpan..." : "Simpan status"}</button>
                    </form>}
                </section>
            </div>}
        </main>
    );
}