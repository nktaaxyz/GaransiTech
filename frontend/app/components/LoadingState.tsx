type TableSkeletonProps = {
    rows?: number;
    columns?: number;
    compact?: boolean;
};

export function TableSkeleton({ rows = 5, columns = 5, compact = false }: TableSkeletonProps) {
    return (
        <div className={`skeleton-table ${compact ? "compact" : ""}`} aria-label="Memuat data" aria-busy="true">
            <div className="skeleton-table-head">
                {Array.from({ length: columns }).map((_, index) => (
                    <span key={`header-${index}`} className="skeleton-line skeleton-line-header" />
                ))}
            </div>

            {Array.from({ length: rows }).map((_, rowIndex) => (
                <div key={`row-${rowIndex}`} className="skeleton-table-row">
                    {Array.from({ length: columns }).map((__, columnIndex) => (
                        <span
                            key={`cell-${rowIndex}-${columnIndex}`}
                            className={`skeleton-line ${columnIndex === 0 ? "skeleton-line-title" : ""}`}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}

export function DashboardSkeleton() {
    return (
        <main className="dashboard-page" aria-label="Memuat dashboard" aria-busy="true">
            <aside className="dashboard-sidebar">
                <div className="dashboard-brand"><span className="skeleton-chip"> </span></div>
                <nav className="dashboard-nav">
                    {Array.from({ length: 6 }).map((_, index) => (
                        <div key={index} className="skeleton-nav-item" />
                    ))}
                </nav>
            </aside>

            <section className="dashboard-main">
                <header className="dashboard-topbar">
                    <div>
                        <div className="skeleton-line skeleton-line-heading" />
                        <div className="skeleton-line skeleton-line-subtitle" />
                    </div>
                    <div className="skeleton-topbar-actions">
                        <div className="skeleton-avatar" />
                        <div className="skeleton-line skeleton-line-small" />
                    </div>
                </header>

                <div className="dashboard-content">
                    <div className="stats-grid">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <div key={index} className="skeleton-stat-card" />
                        ))}
                    </div>

                    <div className="dashboard-grid">
                        <div className="skeleton-block large" />
                        <div className="skeleton-block" />
                    </div>
                </div>
            </section>
        </main>
    );
}
