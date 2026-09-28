"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createWarranty, deleteWarranty, getProductUnits, getToken, getWarranties, updateWarranty, type ProductUnit, type Warranty } from "../lib/api";

const statusLabels: Record<string, string> = { active: "Aktif", expiring: "Segera berakhir", expired: "Berakhir" };

export default function WarrantiesPage() {
    const router = useRouter();
    const [warranties, setWarranties] = useState<Warranty[]>([]);
    const [units, setUnits] = useState<ProductUnit[]>([]);
    const [editingWarranty, setEditingWarranty] = useState<Warranty | null>(null);
    const [query, setQuery] = useState("");
    const [status, setStatus] = useState("");
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");

    async function refresh(nextQuery = query, nextStatus = status) { setWarranties(await getWarranties(nextQuery, nextStatus)); }

    useEffect(() => {
        if (!getToken()) { router.replace("/login"); return; }
        Promise.all([getWarranties(), getProductUnits()]).then(([loadedWarranties, loadedUnits]) => { setWarranties(loadedWarranties); setUnits(loadedUnits); }).catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : "Data garansi tidak dapat dimuat.")).finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault(); setIsSaving(true); setFormError("");
        const data = new FormData(event.currentTarget);
        const input = { product_unit_id: Number(data.get("product_unit_id")), start_date: String(data.get("start_date")), end_date: String(data.get("end_date")), notes: String(data.get("notes") || "") };
        try { if (editingWarranty) await updateWarranty(editingWarranty.id, input); else await createWarranty(input); await refresh(); closeForm(); } catch (requestError) { setFormError(requestError instanceof Error ? requestError.message : "Garansi tidak dapat disimpan."); } finally { setIsSaving(false); }
    }

    async function handleDelete(warranty: Warranty) {
        if (!window.confirm(`Hapus garansi ${warranty.warranty_code}?`)) return;
        try { setError(""); await deleteWarranty(warranty.id); await refresh(); } catch (requestError) { setError(requestError instanceof Error ? requestError.message : "Garansi tidak dapat dihapus."); }
    }

    function closeForm() { setIsFormOpen(false); setEditingWarranty(null); setFormError(""); }

    return (
        <main className="claims-page">
            <header className="page-header"><Link className="back-link" href="/">← Dashboard</Link><div className="page-header-content"><div><p className="dashboard-kicker">MASTER GARANSI</p><h1>Garansi produk</h1><p>Registrasikan unit dan pantau masa berlaku garansi pelanggan.</p></div><button className="primary-action" type="button" onClick={() => { setEditingWarranty(null); setIsFormOpen(true); }}>+ Daftarkan garansi</button></div></header>
            <section className="claims-page-content">{error && <p className="form-error" role="alert">{error}</p>}<section className="claims-list-panel"><div className="claims-list-toolbar"><div><h2>Semua garansi</h2><span>{warranties.length} data ditemukan</span></div><div className="claim-filters"><label className="search-field"><span>⌕</span><input value={query} onChange={(event) => setQuery(event.target.value)} onKeyDown={(event) => event.key === "Enter" && refresh()} placeholder="Cari kode, serial, pelanggan..." /></label><select value={status} onChange={(event) => { setStatus(event.target.value); refresh(query, event.target.value); }} aria-label="Filter status garansi"><option value="">Semua status</option><option value="active">Aktif</option><option value="expiring">Segera berakhir</option><option value="expired">Berakhir</option></select></div></div><div className="claims-table-wrap">{isLoading ? <div className="empty-state"><strong>Memuat data garansi...</strong></div> : warranties.length === 0 ? <div className="empty-state"><strong>Garansi tidak ditemukan</strong><span>Coba ubah kata kunci atau daftarkan garansi baru.</span></div> : <table className="claims-table claims-page-table"><thead><tr><th>Kode garansi</th><th>Pelanggan</th><th>Produk / serial</th><th>Periode</th><th>Status</th><th /></tr></thead><tbody>{warranties.map((warranty) => <tr key={warranty.id}><td><strong>{warranty.warranty_code}</strong><small>ID #{warranty.id.toString().padStart(3, "0")}</small></td><td>{warranty.product_unit.customer?.name || "-"}</td><td><strong>{warranty.product_unit.product?.name || "-"}</strong><small>{warranty.product_unit.serial_number}</small></td><td>{new Date(warranty.start_date).toLocaleDateString("id-ID")} - {new Date(warranty.end_date).toLocaleDateString("id-ID")}</td><td><span className={`status-badge status-${warranty.status}`}>{statusLabels[warranty.status]}</span></td><td><button className="table-action" type="button" onClick={() => { setEditingWarranty(warranty); setIsFormOpen(true); }}>Edit</button><button className="table-action table-action-danger" type="button" onClick={() => handleDelete(warranty)}>Hapus</button></td></tr>)}</tbody></table>}</div></section></section>
            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={closeForm}><section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="warranty-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={closeForm} aria-label="Tutup">×</button><p className="dashboard-kicker">{editingWarranty ? "EDIT GARANSI" : "GARANSI BARU"}</p><h2 id="warranty-modal-title">{editingWarranty ? "Edit garansi" : "Daftarkan garansi"}</h2>{formError && <p className="form-error" role="alert">{formError}</p>}<form onSubmit={handleSubmit}><label>Unit produk<select name="product_unit_id" required defaultValue={editingWarranty?.product_unit.id.toString() || ""}><option value="" disabled>Pilih unit produk</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.serial_number} — {unit.product?.name || "Produk"} / {unit.customer?.name || "Tanpa pelanggan"}</option>)}</select></label><label>Tanggal mulai<input name="start_date" type="date" required defaultValue={editingWarranty?.start_date || ""} /></label><label>Tanggal berakhir<input name="end_date" type="date" required defaultValue={editingWarranty?.end_date || ""} /></label><label>Catatan<textarea name="notes" rows={3} defaultValue={editingWarranty?.notes || ""} /></label><div className="modal-actions"><button className="secondary-action" type="button" onClick={closeForm}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : editingWarranty ? "Simpan perubahan" : "Simpan garansi"}</button></div></form></section></div>}
        </main>
    );
}
