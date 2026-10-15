# Manual device checklist

Run once per release on real devices; automated runs cover Chromium at 390, 820 and 1440 px and WebKit (Safari engine) at 1440 px.

| Device | Browser | Check |
|---|---|---|
| iPhone (small, 375 px) | Safari | Home, a post, 404; rail button; terminal bar; no horizontal scroll; tap targets |
| iPhone (large) | Safari | Same; landscape too |
| Android phone | Chrome | Same; dark and light |
| iPad (portrait 820 px, landscape 1180 px) | Safari | Rail visible only above 900 px container width; slider swipe |
| Laptop 1440 px | Safari, Chrome, Firefox, Edge | All views; keyboard only pass; terminal |
| Desktop 1920 px and 2560 px | Chrome | Shell stops growing at 1480 px; dock stays in place |
| Any | Zoom 200 percent and 400 percent | Reflow without horizontal scroll |
| Any | Reduced motion on | Slider does not autoplay; no caret animation |
| Any | Screen reader (VoiceOver) | Landmarks, skip link, slider labels, terminal log announcements |

Record the date, device and findings in the release evidence report.
