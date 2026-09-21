# Order cancellation is gated by role and current status

The original left `CANCELLED` reachable from any state with no gate on who could set it or when —
flagged as a gap in `idea/02-features.md`. Refund handling is out of scope for this phase (no
payment integration yet); this decision covers only the state-machine gate.

Chosen: `Customer` may cancel only while the order is `PENDING` (before the restaurant has
engaged with it at all). `Manager` (of the restaurant's owning company) may cancel while
`PENDING`, `CONFIRMED`, or `PREPARING` — not once any dish has reached `ready` and the order has
moved to `AWAITING_DELIVERY`/`AWAITING_PICKUP`, since food is effectively done at that point.
`Admin` has an unrestricted override and may cancel from any non-terminal state, as an escape
hatch for disputes.
