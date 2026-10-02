"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { getClaimReport, getToken, getWarrantyReport, type Claim, type Paginated, type Warranty } from "../lib/api";

type ReportType = "warranties" | "claims";
type ReportRow = Warranty | Claim;

const claimStatusLabels: Record<string, string> = {
    received: "Diterima",
    forwarded_to_vendor: "Diteruskan ke vendor",
    processing_by_vendor: "Diproses vendor",
    completed: "Selesai",
    rejected: "Ditolak",
};

const warrantyStatusLabels: Record<string, string> = { active: "Aktif", expiring: "Segera berakhir", expired: "Berakhir" };

export default function ReportsPage() {
    const router = useRouter();
    const [reportType, setReportType] = useState<ReportType>("warranties");
    const [rows, setRows] = useState<ReportRow[]>([]);
    const [from, setFrom] = useState("");
    const [to, setTo] = useState("");
    const [query, setQuery] = useState("");
    const [appliedQuery, setAppliedQuery] = useState("");
    const [status, setStatus] = useState("");
    const [page, setPage] = useState(1);
    const [pagination, setPagination] = useState<Pick<Paginated<Warranty>, "current_page" | "last_page" | "total">>({ current_page: 1, last_page: 1, total: 0 });
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        if (!getToken()) { router.replace("/login"); return; }
    }, [router]);

    useEffect(() => {
        const timeout = window.setTimeout(() => { setAppliedQuery(query); setPage(1); }, 350);
        return () => window.clearTimeout(timeout);
    }, [query]);

    useEffect(() => {
        if (!getToken()) return;
        let isCurrent = true;
        const params = { from, to, search: appliedQuery, status, page };
        const request = reportType === "warranties" ? getWarrantyReport(params) : getClaimReport(params);
        request.then((response) => {
            if (!isCurrent) return;
            setError("");
            setRows(response.data);
            setPagination({ current_page: response.current_page, last_page: response.last_page, total: response.total });
        }).catch((requestError: unknown) => {
            if (isCurrent) setError(requestError instanceof Error ? requestError.message : "Laporan tidak dapat dimuat.");
        }).finally(() => { if (isCurrent) setIsLoading(false); });
        return () => { isCurrent = false; };
    }, [reportType, from, to, appliedQuery, status, page]);

    function switchReport(type: ReportType) {
        setReportType(type);
        setStatus("");
        setPage(1);
        setIsLoading(true);
    }

    function changePeriod(field: "from" | "to", value: string) {
        if (field === "from") setFrom(value);
        else setTo(value);
        setPage(1);
        setIsLoading(true);
    }

    return (
        <main className="claims-page">
            <header className="page-header">
                <Link className="back-link" href="/">← Dashboard</Link>
                <div className="page-header-content">
                    <div><p className="dashboard-kicker">ANALISIS</p><h1>Laporan</h1><p>Tinjau registrasi garansi dan penanganan klaim berdasarkan periode.</p></div>
                </div>
            </header>
            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                <section className="claims-list-panel report-panel">
                    <div className="report-toolbar">
                        <div className="report-tabs" role="tablist" aria-label="Jenis laporan">
                            <button type="button" role="tab" aria-selected={reportType === "warranties"} className={reportType === "warranties" ? "active" : ""} onClick={() => switchReport("warranties")}>Garansi</button>
                            <button type="button" role="tab" aria-selected={reportType === "claims"} className={reportType === "claims" ? "active" : ""} onClick={() => switchReport("claims")}>Klaim</button>
                        </div>
                        <span className="report-total">{pagination.total} data</span>
                    </div>
                    <div className="report-filters">
                        <label className="search-field"><span aria-hidden="true">⌕</span><input value={query} onChange={(event) => { setQuery(event.target.value); setIsLoading(true); }} placeholder="Cari kode, produk, pelanggan..." aria-label="Cari laporan" /></label>
                        <label>Dari<input type="date" value={from} max={to || undefined} onChange={(event) => changePeriod("from", event.target.value)} /></label>
                        <label>Sampai<input type="date" value={to} min={from || undefined} onChange={(event) => changePeriod("to", event.target.value)} /></label>
                        <label>Status<select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); setIsLoading(true); }}><option value="">Semua status</option>{reportType === "warranties" ? <><option value="active">Aktif</option><option value="expiring">Segera berakhir</option><option value="expired">Berakhir</option></> : Object.entries(claimStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
                    </div>
                    <div className="claims-table-wrap">
                        {isLoading ? <div className="empty-state"><strong>Memuat laporan...</strong></div> : rows.length === 0 ? <div className="empty-state"><strong>Data tidak ditemukan</strong><span>Ubah periode, kata kunci, atau status laporan.</span></div> : <table className="claims-table claims-page-table">
                            {reportType === "warranties" ? <><thead><tr><th>Kode garansi</th><th>Pelanggan</th><th>Produk / serial</th><th>Periode garansi</th><th>Status</th></tr></thead><tbody>{(rows as Warranty[]).map((warranty) => <tr key={warranty.id}><td><strong>{warranty.warranty_code}</strong></td><td>{warranty.product_unit.customer?.name || "-"}</td><td><strong>{warranty.product_unit.product?.name || "-"}</strong><small>{warranty.product_unit.serial_number}</small></td><td>{new Date(warranty.start_date).toLocaleDateString("id-ID")} - {new Date(warranty.end_date).toLocaleDateString("id-ID")}</td><td><span className={`status-badge status-${warranty.status}`}>{warrantyStatusLabels[warranty.status]}</span></td></tr>)}</tbody></> : <><thead><tr><th>Kode klaim</th><th>Pelanggan</th><th>Produk / serial</th><th>Tanggal klaim</th><th>Status</th></tr></thead><tbody>{(rows as Claim[]).map((claim) => <tr key={claim.id}><td><strong>{claim.claim_code}</strong></td><td>{claim.customer?.name || "-"}</td><td><strong>{claim.product?.name || "-"}</strong><small>{claim.serial_number || "Tanpa serial number"}</small></td><td>{new Date(claim.claim_date).toLocaleDateString("id-ID", { dateStyle: "medium" })}</td><td><span className={`status-badge status-${claim.status}`}>{claimStatusLabels[claim.status] || claim.status}</span></td></tr>)}</tbody></>}</table>}
                    </div>
                    {pagination.last_page > 1 && <nav className="pagination-controls" aria-label="Halaman laporan"><button type="button" className="secondary-action" disabled={page <= 1} onClick={() => { setIsLoading(true); setPage((currentPage) => currentPage - 1); }}>Sebelumnya</button><span>Halaman {pagination.current_page} dari {pagination.last_page}</span><button type="button" className="secondary-action" disabled={page >= pagination.last_page} onClick={() => { setIsLoading(true); setPage((currentPage) => currentPage + 1); }}>Berikutnya</button></nav>}
                </section>
            </section>
        </main>
    );
}