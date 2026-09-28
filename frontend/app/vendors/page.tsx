"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createVendor, deleteVendor, getToken, getVendors, updateVendor, type Vendor } from "../lib/api";

export default function VendorsPage() {
    const router = useRouter();
    const [vendors, setVendors] = useState<Vendor[]>([]);
    const [editingVendor, setEditingVendor] = useState<Vendor | null>(null);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");

    async function refresh() {
        setVendors(await getVendors());
    }

    useEffect(() => {
        if (!getToken()) { router.replace("/login"); return; }
        getVendors().then(setVendors).catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : "Data vendor tidak dapat dimuat.")).finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsSaving(true);
        setFormError("");
        const data = new FormData(event.currentTarget);
        const input = {
            name: String(data.get("name")),
            contact_person: String(data.get("contact_person") || ""),
            phone: String(data.get("phone") || ""),
            email: String(data.get("email") || ""),
        };
        try {
            if (editingVendor) await updateVendor(editingVendor.id, input);
            else await createVendor(input);
            await refresh();
            closeForm();
        } catch (requestError) {
            setFormError(requestError instanceof Error ? requestError.message : "Vendor tidak dapat disimpan.");
        } finally {
            setIsSaving(false);
        }
    }

    async function handleDelete(vendor: Vendor) {
        if (!window.confirm(`Hapus vendor ${vendor.name}?`)) return;
        try {
            setError("");
            await deleteVendor(vendor.id);
            await refresh();
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : "Vendor tidak dapat dihapus.");
        }
    }

    function closeForm() {
        setIsFormOpen(false);
        setEditingVendor(null);
        setFormError("");
    }

    return (
        <main className="claims-page">
            <header className="page-header">
                <Link className="back-link" href="/">← Dashboard</Link>
                <div className="page-header-content">
                    <div><p className="dashboard-kicker">MASTER DATA</p><h1>Vendor</h1><p>Kelola vendor atau pemasok produk.</p></div>
                    <button className="primary-action" type="button" onClick={() => { setEditingVendor(null); setIsFormOpen(true); }}>+ Tambah vendor</button>
                </div>
            </header>
            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                <section className="claims-list-panel">
                    <div className="claims-list-toolbar"><div><h2>Semua vendor</h2><span>{vendors.length} data ditemukan</span></div></div>
                    <div className="claims-table-wrap">
                        {isLoading ? <div className="empty-state"><strong>Memuat vendor...</strong></div> : vendors.length === 0 ? <div className="empty-state"><strong>Belum ada vendor</strong><span>Tambahkan vendor sebelum membuat produk.</span></div> : (
                            <table className="claims-table claims-page-table"><thead><tr><th>Nama</th><th>Kontak</th><th>Telepon</th><th>Produk</th><th /></tr></thead>
                                <tbody>{vendors.map((vendor) => <tr key={vendor.id}><td><strong>{vendor.name}</strong></td><td>{vendor.contact_person || "-"}</td><td>{vendor.phone || "-"}</td><td>{vendor.products_count ?? 0}</td><td><button className="table-action" type="button" onClick={() => { setEditingVendor(vendor); setIsFormOpen(true); }}>Edit</button><button className="table-action table-action-danger" type="button" onClick={() => handleDelete(vendor)}>Hapus</button></td></tr>)}</tbody>
                            </table>
                        )}
                    </div>
                </section>
            </section>
            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={closeForm}><section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="vendor-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={closeForm} aria-label="Tutup">×</button><p className="dashboard-kicker">{editingVendor ? "EDIT VENDOR" : "VENDOR BARU"}</p><h2 id="vendor-modal-title">{editingVendor ? "Edit vendor" : "Tambah vendor"}</h2>{formError && <p className="form-error" role="alert">{formError}</p>}<form onSubmit={handleSubmit}><label>Nama vendor<input name="name" required defaultValue={editingVendor?.name || ""} /></label><label>Nama kontak<input name="contact_person" defaultValue={editingVendor?.contact_person || ""} /></label><label>Nomor telepon<input name="phone" defaultValue={editingVendor?.phone || ""} /></label><label>Email<input name="email" type="email" defaultValue={editingVendor?.email || ""} /></label><div className="modal-actions"><button className="secondary-action" type="button" onClick={closeForm}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : editingVendor ? "Simpan perubahan" : "Simpan vendor"}</button></div></form></section></div>}
        </main>
    );
}
