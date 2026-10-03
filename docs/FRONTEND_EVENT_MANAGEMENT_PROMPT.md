# Frontend Event Management Implementation Prompt

Use the prompt below with any AI coding assistant (v0, Cursor, Claude Code, ChatGPT, etc.) to generate or update the frontend implementation for Event Management in `eventsos-auth-web`.

---

```markdown
You are an expert React + TypeScript frontend engineer. Your task is to implement the complete Event Management module for "eventsOs", an event management SaaS platform.

### Tech Stack
- Framework: React (Vite) + TypeScript
- Styling: Tailwind CSS (modern, dark/light sleek dashboard aesthetic)
- Data Fetching: TanStack Query (React Query)
- HTTP Client: Axios
- Icons: Lucide React (`lucide-react`)
- Notifications: `react-hot-toast` or similar toast library

---

### Backend API Specifications
- **Base URL**: `http://localhost:8000/api/v1` (or `VITE_API_BASE_URL`)
- **Headers**:
  ```http
  Authorization: Bearer <access_token>
  X-Organizer-UUID: <active_organizer_uuid>
  Content-Type: application/json
  Accept: application/json
  ```
- **Envelope Standard**: All responses return `{ "success": boolean, "message": string, "data": T }`.

---

### Key Endpoints to Integrate

1. **Get Single Organizer Event**:
   `GET /api/v1/organizers/:orgUuid/events/:eventUuid`
   Returns full event details with `location`, `schedule`, `scheduleItems`, `lineup`, `ticketTypes`, `faqs`, and `galleryUrls`.

2. **Event Real-time Statistics / Overview**:
   `GET /api/v1/organizers/:orgUuid/events/:eventUuid/stats`
   Returns:
   ```json
   {
     "totalCapacity": 500,
     "isCapacityUnlimited": false,
     "soldTickets": 120,
     "reservedTickets": 15,
     "remainingTickets": 365,
     "totalRevenue": 1800000.0,
     "currency": "NGN",
     "checkedInCount": 42,
     "favoritesCount": 85,
     "ticketBreakdown": [
       {
         "id": "uuid",
         "name": "Early Bird",
         "type": "paid",
         "price": 15000,
         "currency": "NGN",
         "quantity": 100,
         "soldQuantity": 100,
         "reservedQuantity": 0,
         "revenue": 1500000,
         "status": "sold_out"
       }
     ]
   }
   ```

3. **Lifecycle Controls**:
   - `POST /api/v1/organizers/:orgUuid/events/:eventUuid/publish` (Requires at least 1 ticket tier; returns 422 if empty)
   - `POST /api/v1/organizers/:orgUuid/events/:eventUuid/unpublish` (Reverts published event back to draft)
   - `POST /api/v1/organizers/:orgUuid/events/:eventUuid/duplicate` (Clones entire event, schedule, lineup, & tickets into a new draft; returns 201)
   - `POST /api/v1/organizers/:orgUuid/events/:eventUuid/cancel`
   - `POST /api/v1/organizers/:orgUuid/events/:eventUuid/archive`
   - `DELETE /api/v1/organizers/:orgUuid/events/:eventUuid`

4. **Create / Update Event**:
   - `POST /api/v1/organizers/:orgUuid/events`
   - `PUT /api/v1/organizers/:orgUuid/events/:eventUuid`
   Payload supports:
   - Basic Info: `title`, `description`, `eventCountry`, `eventType` (`physical`, `virtual`, `hybrid`), `eventFormat`, `eventCategory` (array of strings), `bannerUrl`.
   - Physical Location: `venueName`, `venueAddress`, `streetAddress`, `city`, `state`, `zip`, `landmark`, `longitude`, `latitude`.
   - Virtual Location: `onlineMeetingUrl`.
   - Schedule: `scheduleType` (`single`, `recurring`), `scheduleTimezone`, `startDate`, `startTime`, `endDate`, `endTime`.
   - Agenda Items: `schedules: [{ day: "Day 1", title: "Keynote", startTime: "09:00", endTime: "10:30", description: "..." }]`.
   - Lineup / Speakers: `lineup: [{ name: "Sarah", role: "Keynote Speaker", avatarUrl: "...", bio: "..." }]`.
   - FAQs: `faqs: [{ question: "Is parking free?", answer: "Yes, on-site." }]`.
   - Media Gallery: `galleryUrls: ["https://..."]`.

5. **Ticket Types Management**:
   - `GET /api/v1/events/:eventUuid/ticket-types`
   - `POST /api/v1/events/:eventUuid/ticket-types` (Creates Single or Group tier: `name`, `type` ['free'|'paid'], `ticket_type` ['single'|'group'], `group_size`, `price`, `currency`, `quantity`, `ticket_capacity` ['limited'|'unlimited'])
   - `PUT /api/v1/ticket-types/:ticketTypeUuid`
   - `DELETE /api/v1/ticket-types/:ticketTypeUuid`

6. **Public View**:
   - `GET /api/v1/explore/events/:slug`

---

### Pages & Components to Build / Update

#### 1. `src/pages/organizer/EventManage.tsx` (Organizer Event Hub)
- **Top Header**:
  - Breadcrumb navigation (`Events > [Event Title]`).
  - Event title, date, venue/link preview, and status badge (`Draft` [gray/amber], `Published` [green], `Cancelled` [red], `Archived` [purple]).
  - Quick Action Buttons:
    - **Publish Event** button (prominent primary action). If the event has 0 ticket types, clicking shows a modal/tooltip: *"Add at least one ticket tier before publishing."*
    - **Unpublish** button (if published, with confirmation dialog warning that public visibility will pause).
    - **Duplicate Event** button (triggers clone, shows loading spinner, and redirects to the cloned draft).
    - **Preview Public Page** link (opens public event page in new tab).
- **KPI Metrics Cards Grid** (powered by `/stats`):
  1. **Gross Revenue**: Formatted currency with ticket sales progress.
  2. **Tickets Sold**: `soldTickets / totalCapacity` with visual progress bar.
  3. **Check-In Rate**: `checkedInCount / soldTickets` percentage.
  4. **Community Saves**: `favoritesCount`.
- **Management Tabs**:
  - **Tab 1: Tickets & Pricing**:
    - List of ticket tiers with price, sold count, remaining count, and status badges (`Active`, `Sold Out`, `Paused`).
    - "+ Create Ticket Tier" modal supporting Single & Group tickets, free/paid, pricing, capacity limit, and sale start/end dates.
    - Edit and Delete actions per tier.
  - **Tab 2: Agenda & Schedule**:
    - Interactive timeline grouped by Day.
    - "+ Add Session" modal (title, start/end time, day, description).
  - **Tab 3: Lineup & Speakers**:
    - Grid of speaker/performer cards with avatar, role, bio, and social links.
    - "+ Add Speaker" modal.
  - **Tab 4: FAQs**:
    - Expandable accordion preview.
    - "+ Add FAQ" inline form / modal.
  - **Tab 5: Gallery & Media**:
    - Banner image preview + multi-image gallery grid.
  - **Tab 6: Settings / Danger Zone**:
    - Cancel Event modal (with optional reason).
    - Archive Event button.
    - Delete Event (soft delete with strict confirmation prompt).

#### 2. `src/pages/organizer/CreateEvent.tsx` & `EditEvent.tsx`
- Multi-step wizard or organized card sections:
  1. Basic Info (Title, Category tags, Description, Banner upload/URL).
  2. Format & Location (Physical / Virtual toggle with appropriate address or meeting link inputs).
  3. Date, Time & Timezone.
  4. Agenda / Schedule items.
  5. Speakers / Lineup.
  6. FAQs and Gallery URLs.
- Seamless draft auto-save and submission validation.

#### 3. `src/pages/EventDetails.tsx` (Public Attendee Landing Page)
- High-converting modern event page:
  - Hero banner with date, time badge, organizer info, and favorite heart toggle.
  - Sticky Ticket Selection card: list of available tiers, quantity increment/decrement, and "Get Tickets" button.
  - About Event rich description.
  - Event Lineup / Speakers carousel or responsive grid.
  - Schedule & Agenda timeline tabs (Day 1, Day 2).
  - FAQs Accordion section.
  - Venue map / directions placeholder.

---

### UX Guardrails & Polish Requirements
1. **Publish Guard**: Intercept publish attempts if `event.ticketTypes.length === 0`.
2. **Action Confirmations**: Always use modals (not `window.confirm`) for destructive actions (Unpublish, Cancel, Delete).
3. **Empty States**: Render polished empty states for tabs with no items (e.g. "No ticket tiers yet", "No speakers added").
4. **Auto-refresh**: Re-fetch stats using TanStack Query when tickets or status changes.

Please write clean, reusable, fully-typed components following these specifications.
```
