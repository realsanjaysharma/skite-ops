# Recovered Authority Share And Summary Model

## Purpose

This file freezes the recovered behavior for authority-facing sharing and text summaries strongly enough for page design, query design, and later implementation specs.

It does not change the broader product rule that approved visibility is the in-system truth.
It only defines how the system prepares authority-ready output for human sharing.

## Core Share Rules

- authority-facing output uses approved green-belt work proof only
- issue uploads are excluded from authority-ready output
- the system may prepare authority-wise bundles and summaries
- human users still control final external sending
- one-click WhatsApp behavior is helper UX, not silent automated message delivery

## Authority WhatsApp Share Model

### Allowed Trigger

The share action is available from the authority-facing filtered view.

Typical context includes:

- present-day approved photos
- filtered historical view by date
- grouped view by belt, supervisor, or work type

### Share Behavior

- one-click Share via WhatsApp is supported
- the system prepares a pre-filled message
- human user still presses Send
- the system does not silently auto-send WhatsApp messages in v1

### Share Content Boundary

The share context should follow the current approved authority view and must not include:

- internal notes
- rejected or hidden proof
- issue-only uploads
- operational chatter

### Template Model

The transcript supports a structured pre-filled message, not a free-form chat dump.

The message should be built from:

- date or filter context
- authority-wise or filtered belt context
- text summary content

The template must stay concise and operational.
It should act as a forwarding aid, not a narrative report.

## Authority Summary Output Model

### Summary Timing

- authority summaries are end-of-day output
- during the day, current-day uploads can exist without final summary generation
- previous-day grouped photos plus summary are the intended stable authority-facing pattern

### Summary Scope

The summary is:

- date-wise
- belt-wise
- text-only
- work-focused

### Summary Structure

Recovered transcript structure is:

```text
Date: <date>
<Belt Name>: <work done summary>
<Belt Name>: <work done summary>
...
```

Conceptual example from the transcripts:

```text
Date: 12 Sept
Belt A: Watering, Cleaning
Belt B: Watering
Belt C: No work
Belt D: Damage repair (fencing)
```

### Summary Content Rules

The summary should contain only essential work information such as:

- watering
- cleaning
- repair work
- other authority-relevant completed work

The summary must not contain:

- internal notes
- issue chatter not meant for authority
- operational discussions
- governance status

### Summary Intent

The summary exists to:

- help authority quickly understand what happened
- make photos meaningful instead of noisy
- reduce back-and-forth clarification

## Relationship To Portal Access

This file does not replace portal access.
Recovered transcript direction still supports authority users having their own read-only pages with filters, download, and WhatsApp share support.

The summary and share helper sit on top of that read-only approved-proof view.
