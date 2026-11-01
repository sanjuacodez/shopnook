# Brand assets

Source for the animated WordPress.org icon in `.wordpress-org/`. It is drawn in
SVG, so it can be changed and re-exported at exact sizes. The banners
(`banner-772x250.jpg`, `banner-1544x500.jpg`) are images, not generated here.

- `icon.html`: an armchair and floor lamp in a frame, with a pin and a coral
  shopping bag. `window.render('A', t)` draws the animation at time `t`
  (0 to 1.35 seconds): the pin is tapped, a ripple spreads and the bag hops.
  The first and last frames are the still design.

## Re-export

Needs Node with `playwright-core` (point `PW_DIR` at the folder that has it)
and Chrome, Python with Pillow, and gifsicle.

```bash
for s in 256 128; do
  PW_DIR=/path/with/playwright node bin/brand/frames.mjs A $s /tmp/frames-$s 34 1.35 "$PWD/bin/brand/icon.html"
  python3 bin/brand/makegif.py /tmp/frames-$s /tmp/icon.gif /tmp/sheet-$s.png
  gifsicle -O3 /tmp/icon.gif -o .wordpress-org/icon-${s}x${s}.gif
done
```

Palette: frame #111111, inside #FAF7F2, background #F1ECE3, bag and ripple #EE6A4D.
