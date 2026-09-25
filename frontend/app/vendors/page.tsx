"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createVendor, getToken, getVendors, type Vendor } from "../lib/api";

export default function VendorsPage() {
    const router = useRouter();
    const [vendors, setVendors] = useState<Vendor[]>([]);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");

    useEffect(() => {
        if (!getToken()) { router.replace("/login"); return; }
        getVendors().then(setVendors).catch((error: unknown) => setError(error instanceof Error ? error.message : "Data vendor tidak dapat dimuat.")).finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault(); setIsSaving(true); setFormError("");
        const data = new FormData(event.currentTarget);
        try {
            await createVendor({ name: String(data.get("name")), contact_person: String(data.get("contact_person") || ""), phone: String(data.get("phone") || ""), email: String(data.get("email") || "") });
            setVendors(await getVendors()); setIsFormOpen(false); event.currentTarget.reset();
        } catch (error) { setFormError(error instanceof Error ? error.message : "Vendor tidak dapat disimpan."); } finally { setIsSaving(false); }
    }

    return <main className="claims-page"><header className="page-header"><Link className="back-link" href="/">← Dashboard</Link><div className="page-header-content"><div><p className="dashboard-kicker">MASTER DATA</p><h1>Vendor</h1><p>Kelola vendor atau pemasok produk.</p></div><button className="primary-action" type="button" onClick={() => setIsFormOpen(true)}>+ Tambah vendor</button></div></header><section className="claims-page-content">{error && <p className="form-error" role="alert">{error}</p>}<section className="claims-list-panel"><div className="claims-list-toolbar"><div><h2>Semua vendor</h2><span>{vendors.length} data ditemukan</span></div></div><div className="claims-table-wrap">{isLoading ? <div className="empty-state"><strong>Memuat vendor...</strong></div> : vendors.length === 0 ? <div className="empty-state"><strong>Belum ada vendor</strong><span>Tambahkan vendor sebelum membuat produk.</span></div> : <table className="claims-table claims-page-table"><thead><tr><th>Nama</th><th>Kontak</th><th>Telepon</th><th>Produk</th></tr></thead><tbody>{vendors.map((vendor) => <tr key={vendor.id}><td><strong>{vendor.name}</strong></td><td>{vendor.contact_person || "-"}</td><td>{vendor.phone || "-"}</td><td>{vendor.products_count ?? 0}</td></tr>)}</tbody></table>}</div></section></section>{isFormOpen && <div className="modal-backdrop" role="presentation" onClick={() => setIsFormOpen(false)}><section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="vendor-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={() => setIsFormOpen(false)} aria-label="Tutup">×</button><p className="dashboard-kicker">VENDOR BARU</p><h2 id="vendor-modal-title">Tambah vendor</h2>{formError && <p className="form-error" role="alert">{formError}</p>}<form onSubmit={handleSubmit}><label>Nama vendor<input name="name" required /></label><label>Nama kontak<input name="contact_person" /></label><label>Nomor telepon<input name="phone" /></label><label>Email<input name="email" type="email" /></label><div className="modal-actions"><button className="secondary-action" type="button" onClick={() => setIsFormOpen(false)}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : "Simpan vendor"}</button></div></form></section></div>}</main>;
}