<x-guest-layout>
    <div class="landing">
        <div class="landing-hero">
            <nav class="landing-nav">
                <div class="auth-brand">
                    <div class="logo-mark">IM</div>
                    <div class="name">Inventory MS</div>
                </div>
                <a href="{{ url('/admin/login') }}" class="btn-hero-secondary">Sign In</a>
            </nav>

            <div class="landing-hero-content">
                <h1>Inventory management, fully accountable.</h1>
                <p class="landing-sub">
                    Every request, approval, and issuance tracked end to end — from the moment
                    someone asks for stock to the moment it's handed over, with nothing changed
                    outside the record.
                </p>
                <div class="landing-cta-row">
                    <a href="{{ url('/admin/login') }}" class="btn-hero-primary">Sign In</a>
                    <a href="#how-it-works" class="btn-hero-secondary">See how it works</a>
                </div>
            </div>
        </div>

        <div class="landing-section" id="how-it-works">
            <div class="landing-section-heading">
                <h2>Three roles, one accountable trail</h2>
                <p>Nobody skips the queue — approval and issuance always stay within what was actually asked for and what's actually in stock.</p>
            </div>

            <div class="landing-steps">
                <div class="landing-step">
                    <div class="step-number">1</div>
                    <div class="step-role">Demander</div>
                    <h3>Order</h3>
                    <p>Requests the items they need. Only products their group is permitted to order even show up as options.</p>
                </div>
                <div class="landing-step">
                    <div class="step-number">2</div>
                    <div class="step-role">Approver</div>
                    <h3>Approve</h3>
                    <p>Reviews each item and approves up to — never more than — the quantity requested, or rejects it with a reason.</p>
                </div>
                <div class="landing-step">
                    <div class="step-number">3</div>
                    <div class="step-role">Storekeeper</div>
                    <h3>Issue</h3>
                    <p>Hands over stock, capped by both what was approved and what's actually on the shelf right now.</p>
                </div>
            </div>
        </div>

        <div class="landing-section" style="padding-top: 0;">
            <div class="landing-section-heading">
                <h2>Everything you'd expect from a real system of record</h2>
                <p>Not just a form — a system that keeps every stock change explainable.</p>
            </div>

            <div class="landing-features">
                <div class="landing-feature">
                    <div class="feature-icon">🔐</div>
                    <h3>Role-based access</h3>
                    <p>Admin, Approver, Storekeeper, Demander, and read-only Supplier — one panel, permissions scoped per role.</p>
                </div>
                <div class="landing-feature">
                    <div class="feature-icon">📦</div>
                    <h3>Real-time stock tracking</h3>
                    <p>Every stock change runs through an append-only ledger — nothing is ever edited directly.</p>
                </div>
                <div class="landing-feature">
                    <div class="feature-icon">⚠</div>
                    <h3>Low-stock alerts</h3>
                    <p>A single global threshold flags products that need replenishing before they run out.</p>
                </div>
                <div class="landing-feature">
                    <div class="feature-icon">🕒</div>
                    <h3>Full audit trail</h3>
                    <p>Every request, decision, and issuance is logged — who did what, when, and why.</p>
                </div>
                <div class="landing-feature">
                    <div class="feature-icon">📊</div>
                    <h3>Reports & export</h3>
                    <p>Products-issued and user-activity reports, filterable by day, month, or year, exportable to CSV.</p>
                </div>
                <div class="landing-feature">
                    <div class="feature-icon">🏷</div>
                    <h3>Ordering permissions</h3>
                    <p>Item groups gate which user groups can order which products — separate from browsing categories.</p>
                </div>
            </div>
        </div>

        <div class="landing-footer">
            Inventory Management System
        </div>
    </div>
</x-guest-layout>
