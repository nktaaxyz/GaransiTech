"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { createClaim, getClaimsPage, getToken, getWarranties, summarizeClaimTranscript, updateClaimStatus, type Claim, type Warranty, type Paginated } from "../lib/api";
import { getClaimPhoto, saveClaimPhoto } from "../lib/claimPhotos";
import { TableSkeleton } from "../components/LoadingState";

const MAX_PHOTO_SIZE = 5 * 1024 * 1024;
const ALLOWED_PHOTO_TYPES = ["image/jpeg", "image/png", "image/webp"];

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

const statusOptions = ["all", "received", "forwarded_to_vendor", "processing_by_vendor", "completed", "rejected"];

type SpeechRecognitionConstructor = new () => SpeechRecognitionController;

interface SpeechRecognitionController {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    onresult: ((event: SpeechRecognitionResultEvent) => void) | null;
    onerror: ((event: SpeechRecognitionErrorEvent) => void) | null;
    onend: (() => void) | null;
    start(): void;
    stop(): void;
}

interface SpeechRecognitionResultEvent {
    resultIndex: number;
    results: ArrayLike<{
        isFinal: boolean;
        0: { transcript: string };
    }>;
}

interface SpeechRecognitionErrorEvent {
    error: string;
}

type SpeechRecognitionWindow = Window & {
    SpeechRecognition?: SpeechRecognitionConstructor;
    webkitSpeechRecognition?: SpeechRecognitionConstructor;
};

export default function ClaimsPage() {
    const router = useRouter();
    const [claims, setClaims] = useState<Claim[]>([]);
    const [warranties, setWarranties] = useState<Warranty[]>([]);
    const [query, setQuery] = useState("");
    const [appliedQuery, setAppliedQuery] = useState("");
    const [status, setStatus] = useState("all");
    const [page, setPage] = useState(1);
    const [pagination, setPagination] = useState<Pick<Paginated<Claim>, "current_page" | "last_page" | "total">>({ current_page: 1, last_page: 1, total: 0 });
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [selectedClaim, setSelectedClaim] = useState<Claim | null>(null);
    const [nextStatus, setNextStatus] = useState("");
    const [isStatusSaving, setIsStatusSaving] = useState(false);
    const [statusError, setStatusError] = useState("");
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");
    const [isSaving, setIsSaving] = useState(false);
    const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null);
    const [selectedClaimPhoto, setSelectedClaimPhoto] = useState<{ claimCode: string; url: string } | null>(null);
    const [photoRefresh, setPhotoRefresh] = useState(0);
    const [photoFeedback, setPhotoFeedback] = useState("");
    const photoInputRef = useRef<HTMLInputElement | null>(null);
    const [damageDescription, setDamageDescription] = useState("");
    const [speechTranscript, setSpeechTranscript] = useState("");
    const [isSummarizing, setIsSummarizing] = useState(false);
    const [aiError, setAiError] = useState("");
    const [isRecording, setIsRecording] = useState(false);
    const [voiceError, setVoiceError] = useState("");
    const [interimTranscript, setInterimTranscript] = useState("");
    const speechRecognitionRef = useRef<SpeechRecognitionController | null>(null);

    function stopVoiceRecognition() {
        const recognition = speechRecognitionRef.current;
        if (!recognition) return;

        speechRecognitionRef.current = null;
        recognition.onresult = null;
        recognition.onerror = null;
        recognition.onend = null;
        recognition.stop();
        setIsRecording(false);
        setInterimTranscript("");
    }

    function toggleVoiceRecognition() {
        if (speechRecognitionRef.current) {
            stopVoiceRecognition();
            return;
        }

        const SpeechRecognition = (window as SpeechRecognitionWindow).SpeechRecognition
            || (window as SpeechRecognitionWindow).webkitSpeechRecognition;
        if (!SpeechRecognition) {
            setVoiceError("Browser ini belum mendukung pengenalan suara. Silakan isi deskripsi secara manual atau gunakan Chrome/Edge.");
            return;
        }

        const recognition = new SpeechRecognition();
        recognition.lang = "id-ID";
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.onresult = (event) => {
            const finalSegments: string[] = [];
            const interimLines: string[] = [];

            for (let index = event.resultIndex; index < event.results.length; index += 1) {
                const line = event.results[index][0].transcript.trim();
                if (!line) continue;
                if (event.results[index].isFinal) finalSegments.push(line);
                else interimLines.push(line);
            }

            if (finalSegments.length > 0) {
                setSpeechTranscript((current) => [current.trim(), finalSegments.join(" ")].filter(Boolean).join(" "));
            }
            setInterimTranscript(interimLines.join(" "));
        };
        recognition.onerror = (event) => {
            const messages: Record<string, string> = {
                "not-allowed": "Izin mikrofon ditolak. Izinkan akses mikrofon di browser atau isi deskripsi secara manual.",
                "service-not-allowed": "Layanan pengenalan suara tidak diizinkan. Silakan isi deskripsi secara manual.",
                "audio-capture": "Mikrofon tidak ditemukan atau sedang digunakan aplikasi lain.",
                "no-speech": "Suara belum terdeteksi. Coba bicara lebih dekat ke mikrofon.",
                network: "Layanan pengenalan suara tidak dapat dijangkau. Periksa koneksi internet lalu coba lagi.",
            };
            setVoiceError(messages[event.error] || "Perekaman suara gagal. Silakan coba lagi atau isi deskripsi secara manual.");
            speechRecognitionRef.current = null;
            setIsRecording(false);
            setInterimTranscript("");
        };
        recognition.onend = () => {
            speechRecognitionRef.current = null;
            setIsRecording(false);
            setInterimTranscript("");
        };

        try {
            recognition.start();
            speechRecognitionRef.current = recognition;
            setVoiceError("");
            setInterimTranscript("");
            setIsRecording(true);
        } catch {
            setVoiceError("Perekaman tidak dapat dimulai. Periksa izin mikrofon dan coba lagi.");
        }
    }

    async function handleSummarizeTranscript() {
        const transcript = speechTranscript.trim();
        if (!transcript) {
            setAiError("Rekam atau tulis transkrip keluhan terlebih dahulu.");
            return;
        }

        setIsSummarizing(true);
        setAiError("");
        try {
            const result = await summarizeClaimTranscript(transcript);
            if (result.points.length === 0) {
                throw new Error("AI belum menghasilkan poin. Coba gunakan transkrip yang lebih jelas.");
            }
            setDamageDescription(result.points.map((point) => `• ${point}`).join("\n"));
        } catch (requestError) {
            setAiError(requestError instanceof Error ? requestError.message : "Poin keluhan belum dapat dibuat.");
        } finally {
            setIsSummarizing(false);
        }
    }

    useEffect(() => () => {
        const recognition = speechRecognitionRef.current;
        if (!recognition) return;
        speechRecognitionRef.current = null;
        recognition.onresult = null;
        recognition.onerror = null;
        recognition.onend = null;
        recognition.stop();
    }, []);

    useEffect(() => {
        const claimCode = selectedClaim?.claim_code;
        if (!claimCode) return;

        let photoUrl: string | null = null;
        let isCurrent = true;

        getClaimPhoto(claimCode)
            .then((photo) => {
                if (isCurrent && photo) {
                    photoUrl = URL.createObjectURL(photo.file);
                    setSelectedClaimPhoto({ claimCode, url: photoUrl });
                }
            })
            .catch((requestError: unknown) => {
                if (isCurrent) {
                    setPhotoFeedback(requestError instanceof Error ? requestError.message : "Foto klaim gagal dibaca.");
                }
            });

        return () => {
            isCurrent = false;
            if (photoUrl) URL.revokeObjectURL(photoUrl);
        };
    }, [selectedClaim?.claim_code, photoRefresh]);

    useEffect(() => {
        if (!getToken()) {
            router.replace("/login");
            return;
        }

        Promise.all([getWarranties("", "active"), getWarranties("", "expiring")])
            .then(([activeWarranties, expiringWarranties]) => {
                setWarranties([...activeWarranties, ...expiringWarranties]);
            })
            .catch((requestError: unknown) => {
                setError(requestError instanceof Error ? requestError.message : "Data klaim tidak dapat dimuat.");
            })
            .finally(() => setIsLoading(false));
    }, [router]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            setAppliedQuery(query);
            setPage(1);
        }, 350);
        return () => window.clearTimeout(timeout);
    }, [query]);

    useEffect(() => {
        if (!getToken()) return;
        getClaimsPage({ search: appliedQuery, status, page })
            .then((response) => {
                setClaims(response.data);
                setPagination({ current_page: response.current_page, last_page: response.last_page, total: response.total });
            })
            .catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : "Data klaim tidak dapat dimuat."))
            .finally(() => setIsLoading(false));
    }, [appliedQuery, status, page]);

    async function refreshClaims(nextPage = page) {
        const response = await getClaimsPage({ search: appliedQuery, status, page: nextPage });
        setClaims(response.data);
        setPagination({ current_page: response.current_page, last_page: response.last_page, total: response.total });
    }

    function validatePhoto(file: File): string | null {
        if (!ALLOWED_PHOTO_TYPES.includes(file.type)) {
            return "Pilih foto berformat JPG, PNG, atau WEBP.";
        }
        if (file.size > MAX_PHOTO_SIZE) {
            return "Ukuran foto maksimal 5 MB.";
        }
        return null;
    }

    async function savePhotoForClaim(claimCode: string, file: File): Promise<void> {
        const validationMessage = validatePhoto(file);
        if (validationMessage) throw new Error(validationMessage);

        await saveClaimPhoto(claimCode, file);
        setPhotoFeedback("Foto tersimpan di browser ini dan tidak diunggah ke server.");
        if (selectedClaim?.claim_code === claimCode) {
            setPhotoRefresh((current) => current + 1);
        }
    }

    function closeClaimForm() {
        stopVoiceRecognition();
        setIsFormOpen(false);
        setSelectedPhoto(null);
        if (photoInputRef.current) photoInputRef.current.value = "";
        setFormError("");
        setVoiceError("");
        setDamageDescription("");
        setSpeechTranscript("");
        setAiError("");
    }

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (speechRecognitionRef.current) {
            setVoiceError("Selesaikan perekaman sebelum menyimpan klaim.");
            return;
        }
        setIsSaving(true);
        setFormError("");
        const form = event.currentTarget;
        const formData = new FormData(form);

        try {
            const createdClaim = await createClaim({
                warranty_id: Number(formData.get("warranty_id")),
                claim_date: String(formData.get("claim_date")),
                damage_description: String(formData.get("damage_description")),
            });
            if (selectedPhoto) {
                try {
                    await savePhotoForClaim(createdClaim.claim_code, selectedPhoto);
                } catch (photoError) {
                    setPhotoFeedback(
                        `Klaim berhasil dicatat, tetapi foto gagal disimpan: ${photoError instanceof Error ? photoError.message : "terjadi kesalahan."}`,
                    );
                }
            }
            setPage(1);
            await refreshClaims(1);
            closeClaimForm();
            form.reset();
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
        const form = event.currentTarget;
        const formData = new FormData(form);
        const nextStatusValue = String(formData.get("status"));

        try {
            const updatedClaim = await updateClaimStatus({
                claimId: selectedClaim.id,
                status: nextStatusValue,
                note: String(formData.get("status_note")),
                ...(nextStatusValue === "forwarded_to_vendor" && {
                    forwarded_date: String(formData.get("forwarded_date")),
                    vendor_reference_number: String(formData.get("vendor_reference_number")),
                }),
                ...(nextStatusValue === "completed" && {
                    resolution_date: String(formData.get("resolution_date")),
                    resolution_note: String(formData.get("resolution_note")),
                }),
            });
            setClaims((currentClaims) => currentClaims.map((claim) => claim.id === updatedClaim.id ? updatedClaim : claim));
            setSelectedClaim(updatedClaim);
            await refreshClaims();
            setNextStatus("");
            form.reset();
        } catch (requestError) {
            setStatusError(requestError instanceof Error ? requestError.message : "Status klaim tidak dapat diperbarui.");
        } finally {
            setIsStatusSaving(false);
        }
    }

    const filteredClaims = claims;
    const timeline = [...(selectedClaim?.status_logs || [])].sort((left, right) =>
        new Date(left.created_at || 0).getTime() - new Date(right.created_at || 0).getTime(),
    );

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
                    <button className="primary-action" type="button" onClick={() => { setFormError(""); setPhotoFeedback(""); setIsFormOpen(true); }}>+ Catat klaim</button>
                </div>
            </header>

            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                {photoFeedback && !selectedClaim && <p className="photo-feedback" role="status">{photoFeedback}</p>}
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
                            <span>{pagination.total} data ditemukan</span>
                        </div>
                        <div className="claim-filters">
                            <label className="search-field"><span>⌕</span><input value={query} onChange={(event) => { setQuery(event.target.value); setIsLoading(true); }} placeholder="Cari kode, pelanggan, produk..." /></label>
                            <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); setIsLoading(true); }} aria-label="Filter status klaim">
                                {statusOptions.map((option) => <option key={option} value={option}>{option === "all" ? "Semua status" : statusLabels[option]}</option>)}
                            </select>
                        </div>
                    </div>

                    <div className="claims-table-wrap">
                        {isLoading ? <TableSkeleton rows={5} columns={6} /> : <table className="claims-table claims-page-table">
                            <thead><tr><th>Klaim</th><th>Pelanggan</th><th>Produk / serial</th><th>Tanggal</th><th>Status</th><th /></tr></thead>
                            <tbody>{filteredClaims.map((claim: Claim) => (
                                <tr key={claim.id}>
                                    <td><strong>{claim.claim_code}</strong><small>ID #{claim.id.toString().padStart(3, "0")}</small></td>
                                    <td>{claim.customer?.name || "-"}</td>
                                    <td><strong>{claim.product?.name || "-"}</strong><small>{claim.serial_number || "Tanpa serial number"}</small></td>
                                    <td>{new Date(claim.claim_date).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })}</td>
                                    <td><span className={`status-badge status-${claim.status}`}>{statusLabels[claim.status] || claim.status}</span></td>
                                    <td><button className="table-action" type="button" onClick={() => { setSelectedClaim(claim); setNextStatus(""); setStatusError(""); }}>Detail →</button></td>
                                </tr>
                            ))}</tbody>
                        </table>}
                        {!isLoading && filteredClaims.length === 0 && <div className="empty-state"><strong>Klaim tidak ditemukan</strong><span>Coba ubah kata kunci atau filter status.</span></div>}
                    </div>
                    {pagination.last_page > 1 && <nav className="pagination-controls" aria-label="Halaman klaim"><button type="button" className="secondary-action" disabled={page <= 1} onClick={() => { setIsLoading(true); setPage((currentPage) => currentPage - 1); }}>Sebelumnya</button><span>Halaman {pagination.current_page} dari {pagination.last_page}</span><button type="button" className="secondary-action" disabled={page >= pagination.last_page} onClick={() => { setIsLoading(true); setPage((currentPage) => currentPage + 1); }}>Berikutnya</button></nav>}
                </section>
            </section>

            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={() => { if (!isSaving && !isSummarizing) closeClaimForm(); }}>
                <section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="claim-modal-title" onClick={(event) => event.stopPropagation()}>
                    <button className="modal-close" type="button" onClick={closeClaimForm} disabled={isSaving || isSummarizing} aria-label="Tutup">×</button>
                    <p className="dashboard-kicker">KLAIM BARU</p>
                    <h2 id="claim-modal-title">Catat klaim garansi</h2>
                    <p className="modal-description">{warranties.length > 0 ? "Pilih garansi yang masa berlakunya mencakup tanggal klaim." : <>Belum ada garansi yang berlaku. <Link href="/warranties">Daftarkan garansi</Link> terlebih dahulu.</>}</p>
                    {formError && <p className="form-error" role="alert">{formError}</p>}
                    {voiceError && <p className="form-error" role="alert">{voiceError}</p>}
                    {aiError && <p className="form-error" role="alert">{aiError}</p>}
                    <form onSubmit={handleSubmit}>
                        <label>Garansi / nomor serial<select name="warranty_id" required defaultValue=""><option value="" disabled>Pilih garansi yang berlaku</option>{warranties.map((warranty) => <option key={warranty.id} value={warranty.id}>{warranty.product_unit.serial_number} — {warranty.product_unit.product?.name || "Produk"} / {warranty.product_unit.customer?.name || "Tanpa pelanggan"}{warranty.status === "expiring" ? " (Segera berakhir)" : ""}</option>)}</select></label>
                        <label>Tanggal klaim<input name="claim_date" type="date" required defaultValue={new Date().toISOString().slice(0, 10)} /></label>
                        <section className="voice-entry" aria-label="Rekam dan susun keluhan">
                            <label>Transkrip keluhan<textarea value={speechTranscript} onChange={(event) => setSpeechTranscript(event.target.value)} placeholder="Hasil rekaman akan muncul di sini dan bisa diedit." rows={3} /></label>
                            <div className="voice-entry-actions">
                                <button className={`voice-record-button${isRecording ? " is-recording" : ""}`} type="button" onClick={toggleVoiceRecognition} disabled={isSaving}>
                                    <span className="voice-record-indicator" aria-hidden="true" />
                                    {isRecording ? "Selesai merekam" : "Rekam keluhan"}
                                </button>
                                <button className="ai-draft-button" type="button" disabled={!speechTranscript.trim() || isRecording || isSaving || isSummarizing} onClick={handleSummarizeTranscript}>{isSummarizing ? "AI sedang menyusun..." : "✦ Buat poin otomatis"}</button>
                            </div>
                            <p className="voice-entry-hint">Rekam keluhan dalam Bahasa Indonesia, lalu minta AI menyusunnya menjadi poin-poin. Periksa dan edit hasil sebelum menyimpan klaim.</p>
                            {interimTranscript && <p className="voice-transcript-preview" role="status">Mendengarkan: {interimTranscript}</p>}
                            <label>Poin keluhan<textarea name="damage_description" placeholder="Hasil susunan AI akan tampil di sini. Kamu juga bisa mengedit poinnya." rows={4} required value={damageDescription} onChange={(event) => setDamageDescription(event.target.value)} /></label>
                        </section>
                        <section className="photo-upload-section" aria-labelledby="claim-photo-label">
                            <div className="photo-upload-heading">
                                <label id="claim-photo-label">Foto bukti kerusakan <span className="photo-upload-hint">Opsional · JPG, PNG, WEBP · maksimal 5 MB</span></label>
                            </div>
                            <div className="photo-picker">
                                <input ref={photoInputRef} className="photo-file-input" id="claim-photo-input" name="photo" type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => {
                                    const file = event.target.files?.[0] || null;
                                    if (file) {
                                        const validationMessage = validatePhoto(file);
                                        if (validationMessage) {
                                            setFormError(validationMessage);
                                            event.target.value = "";
                                            setSelectedPhoto(null);
                                            return;
                                        }
                                    }
                                    setFormError("");
                                    setSelectedPhoto(file);
                                }} />
                                <div className="photo-picker-toolbar"><span>File lampiran</span><label className="photo-picker-browse" htmlFor="claim-photo-input">{selectedPhoto ? "Ganti file" : "Pilih file"}</label></div>
                                {selectedPhoto ? <div className="photo-file-row">
                                    <span className="photo-file-type" aria-hidden="true">IMG</span>
                                    <span className="photo-picker-filename">{selectedPhoto.name}</span>
                                    <button className="photo-file-remove" type="button" onClick={() => { setSelectedPhoto(null); if (photoInputRef.current) photoInputRef.current.value = ""; }}>Hapus</button>
                                </div> : <p className="photo-picker-empty">Belum ada file yang dipilih.</p>}
                            </div>
                            <p className="photo-storage-note">Foto tersimpan di browser ini saja; tidak diunggah ke server.</p>
                        </section>
                        <div className="modal-actions"><button className="secondary-action" type="button" onClick={closeClaimForm} disabled={isSaving || isSummarizing}>Batal</button><button className="primary-action" type="submit" disabled={isSaving || isRecording || isSummarizing || warranties.length === 0}>{isSaving ? "Menyimpan..." : "Simpan klaim"}</button></div>
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
                    <div className="claim-detail-note"><small>Poin keluhan</small><p>{selectedClaim.damage_description || "Belum ada poin keluhan."}</p></div>
                    <div className="claim-detail-photo"><small>Foto bukti kerusakan</small>{selectedClaimPhoto?.claimCode === selectedClaim.claim_code ? <a href={selectedClaimPhoto.url} target="_blank" rel="noreferrer"><Image src={selectedClaimPhoto.url} alt={`Foto bukti klaim ${selectedClaim.claim_code}`} width={720} height={480} unoptimized /></a> : <p>Belum ada foto bukti yang tersimpan di browser ini.</p>}{photoFeedback && <p className="photo-feedback" role="status">{photoFeedback}</p>}<div className="photo-picker detail-photo-picker"><input className="photo-file-input" id={`detail-photo-${selectedClaim.id}`} type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => {
                        const file = event.target.files?.[0];
                        if (!file) return;
                        event.target.value = "";
                        savePhotoForClaim(selectedClaim.claim_code, file).catch((photoError: unknown) => {
                            setPhotoFeedback(photoError instanceof Error ? photoError.message : "Foto gagal disimpan.");
                        });
                    }} /><label className="photo-picker-label" htmlFor={`detail-photo-${selectedClaim.id}`}><span className="photo-picker-browse">Pilih file</span><span className="photo-picker-filename">JPG, PNG, WEBP · maks 5 MB</span></label></div><span className="photo-storage-note">Foto lokal untuk browser ini saja; tidak dikirim ke server.</span></div>
                    {selectedClaim.note && <div className="claim-detail-note"><small>Catatan</small><p>{selectedClaim.note}</p></div>}
                    <section className="claim-timeline" aria-label="Riwayat status klaim">
                        <div className="section-heading"><div><p className="dashboard-kicker">RIWAYAT</p><h3>Perjalanan klaim</h3></div></div>
                        {timeline.length === 0 ? <p className="timeline-empty">Belum ada riwayat status.</p> : <ol>{timeline.map((log) => <li key={log.id}><span className="timeline-marker" /><div><strong>{log.old_status ? `${statusLabels[log.old_status] || log.old_status} → ` : ""}{statusLabels[log.new_status] || log.new_status}</strong><p>{log.note}</p><small>{log.created_at ? new Date(log.created_at).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" }) : "Waktu tidak tersedia"}{log.changed_by?.name ? ` · ${log.changed_by.name}` : ""}</small></div></li>)}</ol>}
                    </section>
                    {nextStatusOptions[selectedClaim.status] && <form className="status-update-form" onSubmit={handleStatusSubmit}>
                        <p className="dashboard-kicker">PERBARUI STATUS</p>
                        {statusError && <p className="form-error" role="alert">{statusError}</p>}
                        <label>Status berikutnya<select name="status" required value={nextStatus} onChange={(event) => setNextStatus(event.target.value)}><option value="" disabled>Pilih status</option>{nextStatusOptions[selectedClaim.status].map((option) => <option key={option} value={option}>{statusLabels[option]}</option>)}</select></label>
                        {nextStatus === "forwarded_to_vendor" && <>
                            <label>Tanggal diteruskan<input name="forwarded_date" type="date" required /></label>
                            <label>Nomor referensi vendor<input name="vendor_reference_number" required placeholder="Masukkan nomor referensi" /></label>
                        </>}
                        {nextStatus === "completed" && <>
                            <label>Tanggal selesai<input name="resolution_date" type="date" required /></label>
                            <label>Catatan penyelesaian<textarea name="resolution_note" rows={3} required placeholder="Tuliskan hasil penyelesaian..." /></label>
                        </>}
                        <label>Catatan penanganan<textarea name="status_note" rows={3} required placeholder="Tuliskan tindakan atau hasil penanganan..." /></label>
                        <button className="primary-action" type="submit" disabled={isStatusSaving}>{isStatusSaving ? "Menyimpan..." : "Simpan status"}</button>
                    </form>}
                </section>
            </div>}
        </main>
    );
}