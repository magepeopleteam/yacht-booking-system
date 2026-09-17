import { __, _n, sprintf } from "@wordpress/i18n";
import { useEffect, useState } from "@wordpress/element";
import { api } from "../api/client";
import { navigate } from "../router";

// Paid statuses only - see BookingRepository::counts_for_dashboard().
function money(stats, amount) {
  return `${stats.currency_symbol || ""}${Number(amount || 0).toFixed(2)}`;
}

const STATUS_ROWS = [
  {
    key: "pending",
    label: __("Pending payment", "magepeople-yacht-booking-system"),
    tone: "orange",
  },
  {
    key: "processing",
    label: __("Processing", "magepeople-yacht-booking-system"),
    tone: "blue",
  },
  {
    key: "on-hold",
    label: __("On hold", "magepeople-yacht-booking-system"),
    tone: "purple",
  },
  {
    key: "completed",
    label: __("Completed", "magepeople-yacht-booking-system"),
    tone: "green",
  },
  {
    key: "cancelled",
    label: __("Cancelled", "magepeople-yacht-booking-system"),
    tone: "red",
  },
];

function goTo(path) {
  return (event) => {
    event.preventDefault();
    navigate(path);
  };
}

function MetricCard({ icon, label, value, detail, tone }) {
  return (
    <div className={`ybs-stat-card is-${tone}`}>
      <div className="ybs-stat-card__top">
        <span className={`ybs-stat-card__icon dashicons ${icon}`} />
      </div>
      <div className="ybs-stat-card__value">{value}</div>
      <div className="ybs-stat-card__label">{label}</div>
      <div className="ybs-stat-card__detail">{detail}</div>
    </div>
  );
}

function statusLabel(status) {
  return STATUS_ROWS.find((row) => row.key === status)?.label || status;
}

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const [error, setError] = useState("");

  useEffect(() => {
    api
      .get("/reports/summary")
      .then(setStats)
      .catch((err) =>
        setError(
          err.message ||
            __("Failed to load stats.", "magepeople-yacht-booking-system"),
        ),
      );
  }, []);

  const totalBookings = Number(stats?.total_bookings || 0);
  const monthShare =
    stats?.revenue_total > 0
      ? Math.min(
          100,
          Math.round(
            (Number(stats.revenue_this_month) / Number(stats.revenue_total)) *
              100,
          ),
        )
      : 0;

  return (
    <div className="ybs-dashboard">
      <section className="ybs-dashboard-hero">
        <div className="ybs-dashboard-hero__content">
          <span className="ybs-dashboard-hero__eyebrow">
            {__("Yacht operations overview", "magepeople-yacht-booking-system")}
          </span>
          <h2>
            {__(
              "Welcome to your booking command center",
              "magepeople-yacht-booking-system",
            )}
          </h2>
          <p>
            {__(
              "Track today’s charters, manage your fleet, and keep every guest journey on course.",
              "magepeople-yacht-booking-system",
            )}
          </p>
          <div className="ybs-dashboard-hero__actions">
            <a
              className="ybs-btn is-primary"
              href="#/yachts/new"
              onClick={goTo("yachts/new")}
            >
              <span className="dashicons dashicons-plus-alt2" />
              {__("Add New Yacht", "magepeople-yacht-booking-system")}
            </a>
            <a
              className="ybs-btn is-hero-secondary"
              href="#/calendar"
              onClick={goTo("calendar")}
            >
              <span className="dashicons dashicons-calendar-alt" />
              {__("Open Calendar", "magepeople-yacht-booking-system")}
            </a>
          </div>
        </div>
        <div className="ybs-dashboard-hero__art" aria-hidden="true">
          <span className="dashicons dashicons-palmtree" />
          <div className="ybs-dashboard-hero__wave" />
        </div>
      </section>

      {error && <div className="ybs-notice is-error">{error}</div>}

      {!stats && !error && (
        <div className="ybs-loading">
          {__("Loading dashboard…", "magepeople-yacht-booking-system")}
        </div>
      )}

      {stats && (
        <>
          <div className="ybs-dashboard-section-heading">
            <div>
              <h3>
                {__("Business snapshot", "magepeople-yacht-booking-system")}
              </h3>
              <p>
                {__(
                  "Live numbers from your bookings and fleet.",
                  "magepeople-yacht-booking-system",
                )}
              </p>
            </div>
            <a href="#/bookings" onClick={goTo("bookings")}>
              {__("View all bookings", "magepeople-yacht-booking-system")}{" "}
              <span aria-hidden="true">→</span>
            </a>
          </div>

          <div className="ybs-stat-grid">
            <MetricCard
              icon="dashicons-tickets-alt"
              tone="orange"
              label={__("Today's Bookings", "magepeople-yacht-booking-system")}
              value={stats.today_bookings}
              detail={__(
                "Charters starting today",
                "magepeople-yacht-booking-system",
              )}
            />
            <MetricCard
              icon="dashicons-calendar-alt"
              tone="blue"
              label={__("Upcoming Bookings", "magepeople-yacht-booking-system")}
              value={stats.upcoming_bookings}
              detail={__(
                "Scheduled from now",
                "magepeople-yacht-booking-system",
              )}
            />
            <MetricCard
              icon="dashicons-palmtree"
              tone="green"
              label={__("Active Yachts", "magepeople-yacht-booking-system")}
              value={stats.active_yachts}
              detail={__(
                "Published and bookable",
                "magepeople-yacht-booking-system",
              )}
            />
            <MetricCard
              icon="dashicons-money-alt"
              tone="purple"
              label={__(
                "Revenue This Month",
                "magepeople-yacht-booking-system",
              )}
              value={money(stats, stats.revenue_this_month)}
              detail={sprintf(
                /* translators: %d: percentage of all-time revenue earned this month. */
                __(
                  "%d%% of all-time revenue",
                  "magepeople-yacht-booking-system",
                ),
                monthShare,
              )}
            />
          </div>

          <div className="ybs-dashboard-grid">
            <section className="ybs-dashboard-panel ybs-dashboard-panel--charters">
              <div className="ybs-dashboard-panel__head">
                <div>
                  <h3>
                    {__("Upcoming charters", "magepeople-yacht-booking-system")}
                  </h3>
                  <p>
                    {__(
                      "Your next active bookings in departure order.",
                      "magepeople-yacht-booking-system",
                    )}
                  </p>
                </div>
                <a href="#/calendar" onClick={goTo("calendar")}>
                  {__("Full calendar", "magepeople-yacht-booking-system")}
                </a>
              </div>

              {stats.upcoming?.length ? (
                <div className="ybs-upcoming-list">
                  {(stats.upcoming || []).map((booking) => (
                    <div className="ybs-upcoming-item" key={booking.id}>
                      <div className="ybs-upcoming-item__date">
                        <span className="dashicons dashicons-calendar-alt" />
                      </div>
                      <div className="ybs-upcoming-item__main">
                        <strong>{booking.yacht_name}</strong>
                        <span>
                          {booking.start_formatted} · {booking.duration}
                        </span>
                      </div>
                      <div className="ybs-upcoming-item__guest">
                        <strong>{booking.guest_name}</strong>
                        <span>
                          {sprintf(
                            /* translators: %d: number of guests on the booking. */
                            _n(
                              "%d guest",
                              "%d guests",
                              booking.guest_count,
                              "magepeople-yacht-booking-system",
                            ),
                            booking.guest_count,
                          )}
                        </span>
                      </div>
                      <div className="ybs-upcoming-item__price">
                        {money(stats, booking.total_price)}
                      </div>
                      <span className={`ybs-badge status-${booking.status}`}>
                        {statusLabel(booking.status)}
                      </span>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="ybs-dashboard-empty">
                  <span className="dashicons dashicons-calendar" />
                  <strong>
                    {__(
                      "Your schedule is clear",
                      "magepeople-yacht-booking-system",
                    )}
                  </strong>
                  <p>
                    {__(
                      "New confirmed charters will appear here automatically.",
                      "magepeople-yacht-booking-system",
                    )}
                  </p>
                  <a
                    className="ybs-btn"
                    href="#/yachts"
                    onClick={goTo("yachts")}
                  >
                    {__("Manage yachts", "magepeople-yacht-booking-system")}
                  </a>
                </div>
              )}
            </section>

            <div className="ybs-dashboard-sidebar">
              <section className="ybs-dashboard-panel">
                <div className="ybs-dashboard-panel__head">
                  <div>
                    <h3>
                      {__("Booking health", "magepeople-yacht-booking-system")}
                    </h3>
                    <p>
                      {sprintf(
                        /* translators: %d: total number of bookings. */
                        __(
                          "%d total bookings",
                          "magepeople-yacht-booking-system",
                        ),
                        totalBookings,
                      )}
                    </p>
                  </div>
                  <span className="ybs-dashboard-total">
                    {money(stats, stats.revenue_total)}
                  </span>
                </div>
                <div className="ybs-status-list">
                  {STATUS_ROWS.map((row) => {
                    const count = Number(stats.status_counts?.[row.key] || 0);
                    const percent = totalBookings
                      ? Math.round((count / totalBookings) * 100)
                      : 0;

                    return (
                      <div className="ybs-status-row" key={row.key}>
                        <div className="ybs-status-row__meta">
                          <span>{row.label}</span>
                          <strong>{count}</strong>
                        </div>
                        <div className="ybs-status-row__track">
                          <span
                            className={`is-${row.tone}`}
                            style={{ width: `${percent}%` }}
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
              </section>

              <section className="ybs-dashboard-panel">
                <div className="ybs-dashboard-panel__head">
                  <div>
                    <h3>
                      {__("Quick actions", "magepeople-yacht-booking-system")}
                    </h3>
                    <p>
                      {__(
                        "Jump straight into daily tasks.",
                        "magepeople-yacht-booking-system",
                      )}
                    </p>
                  </div>
                </div>
                <div className="ybs-quick-actions">
                  <a href="#/yachts/new" onClick={goTo("yachts/new")}>
                    <span className="dashicons dashicons-plus-alt2" />
                    <span>
                      <strong>
                        {__("Add a yacht", "magepeople-yacht-booking-system")}
                      </strong>
                      <small>
                        {__(
                          "Grow your bookable fleet",
                          "magepeople-yacht-booking-system",
                        )}
                      </small>
                    </span>
                  </a>
                  <a href="#/bookings" onClick={goTo("bookings")}>
                    <span className="dashicons dashicons-tickets-alt" />
                    <span>
                      <strong>
                        {__(
                          "Manage bookings",
                          "magepeople-yacht-booking-system",
                        )}
                      </strong>
                      <small>
                        {__(
                          "Review guests and statuses",
                          "magepeople-yacht-booking-system",
                        )}
                      </small>
                    </span>
                  </a>
                  <a href="#/settings" onClick={goTo("settings")}>
                    <span className="dashicons dashicons-admin-generic" />
                    <span>
                      <strong>
                        {__(
                          "Booking settings",
                          "magepeople-yacht-booking-system",
                        )}
                      </strong>
                      <small>
                        {__(
                          "Payments, pricing and email",
                          "magepeople-yacht-booking-system",
                        )}
                      </small>
                    </span>
                  </a>
                  <a href="#/user-guide" onClick={goTo("user-guide")}>
                    <span className="dashicons dashicons-book-alt" />
                    <span>
                      <strong>
                        {__(
                          "Open user guide",
                          "magepeople-yacht-booking-system",
                        )}
                      </strong>
                      <small>
                        {__(
                          "Get help with setup",
                          "magepeople-yacht-booking-system",
                        )}
                      </small>
                    </span>
                  </a>
                </div>
              </section>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
