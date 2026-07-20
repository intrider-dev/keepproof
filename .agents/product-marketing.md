# KeepProof product marketing context

## Product

KeepProof is a privacy-first receipt, return-window, and warranty tracker for individuals and families. Its promise is simple: **Never miss a return window or warranty deadline.**

The product stores proof of purchase privately, extracts useful details locally when possible, asks the user to confirm every extracted value, calculates explainable deadlines, sends reminders, and prepares portable exports for calendar, claim, or account use.

## Primary audience

- People who lose receipts across email, drawers, and photo libraries.
- Families who share purchases and need one reliable place for deadlines and documents.
- Privacy-conscious users who want local processing and a complete data export.
- Recruiters and senior engineers evaluating the repository as a production-oriented portfolio project are a secondary audience, not the voice of the consumer UI.

## User problem

Receipts are easy to lose and return or warranty dates are easy to forget. When a product fails or needs to go back, the user must reconstruct the purchase, find the proof, and determine whether the deadline has passed. Existing approaches are fragmented, require manual calendar work, or send purchase data to an opaque external provider.

## Differentiation

- Local-first OCR and deterministic demo extraction; no paid provider is required.
- External AI is off by default and requires explicit per-document consent.
- Extracted values never become authoritative until a person confirms them.
- Explainable deadline calculations rather than a black-box date.
- Private S3-compatible storage, short-lived authorized downloads, and complete export/deletion controls.
- A household model designed for shared use without exposing one household to another.

## Positioning and voice

- Calm, direct, trustworthy, and useful.
- Consumer language in the UI; technical detail belongs in documentation.
- Prefer concrete outcomes over broad claims.
- Never imply legal advice, guaranteed eligibility, perfect OCR, or guaranteed delivery.
- Never invent customers, testimonials, usage, revenue, time savings, or performance figures.
- Privacy claims must describe actual behavior and limits.

## Primary actions

- Landing page: **Try the local demo** first, with **Create a private account** as the secondary action.
- Authenticated empty state: **Add your first purchase**.
- Extraction review: **Confirm purchase details**.
- Approaching deadline: **Prepare claim package** or **Add to calendar**, depending on context.

## Objections to answer

- “Will my receipt be sent to an AI provider?” — Not unless the optional feature is enabled and the user explicitly consents for that document.
- “Can I correct OCR mistakes?” — Every extracted field is reviewed before deadlines activate.
- “Am I locked in?” — Account and household data can be exported; calendar events use ICS.
- “Does this know the law in my country?” — No. KeepProof tracks the terms entered or extracted and does not provide legal advice.
- “Can another household see my documents?” — Authorization and tenant-scoped data constraints prevent cross-household access; the repository includes isolation tests.

## Proof available at launch

Only verifiable repository evidence: passing tests, runnable demo fixtures, generated screenshots, documented local measurements, security controls, and linked implementation files. No social proof or business impact claims.
