/* ===========================================================
   PRESTIGE — ambient galaxy dot-field
   A subtle, slow-drifting field of gold/cream specks that sits
   behind all content (z-index:-1). Premium, low-density, gentle
   twinkle. Honors reduced-motion and pauses when tab is hidden.
   =========================================================== */
(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion:reduce)').matches;

  var canvas = document.createElement('canvas');
  canvas.setAttribute('aria-hidden', 'true');
  canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:-1';
  // place it behind content but above the body background
  if (document.body) { document.body.appendChild(canvas); }
  var ctx = canvas.getContext('2d');
  var dpr = Math.min(window.devicePixelRatio || 1, 2);

  var W = 0, H = 0, stars = [];

  function build(){
    W = window.innerWidth;
    H = window.innerHeight;
    canvas.width = W * dpr;
    canvas.height = H * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    // density scales with viewport, kept deliberately sparse
    var count = Math.min(140, Math.round((W * H) / 14000));
    stars = [];
    for (var i = 0; i < count; i++){
      var bright = Math.random() < 0.16;           // a few brighter "feature" specks
      stars.push({
        x: Math.random() * W,
        y: Math.random() * H,
        r: bright ? (1.4 + Math.random() * 1.3) : (0.5 + Math.random() * 1.0),
        base: bright ? (0.35 + Math.random() * 0.3) : (0.08 + Math.random() * 0.22),
        tw: Math.random() * Math.PI * 2,           // twinkle phase
        tws: 0.006 + Math.random() * 0.012,        // twinkle speed
        vx: (Math.random() - 0.5) * 0.05,          // very slow drift
        vy: (Math.random() - 0.5) * 0.05,
        gold: Math.random() < 0.7,                 // gold vs cream
        glow: bright
      });
    }
  }

  function paint(animate){
    ctx.clearRect(0, 0, W, H);
    for (var i = 0; i < stars.length; i++){
      var s = stars[i];
      if (animate){
        s.x += s.vx; s.y += s.vy; s.tw += s.tws;
        if (s.x < -2) s.x = W + 2; else if (s.x > W + 2) s.x = -2;
        if (s.y < -2) s.y = H + 2; else if (s.y > H + 2) s.y = -2;
      }
      var a = s.base + (animate ? Math.sin(s.tw) * (s.base * 0.6) : 0);
      if (a < 0) a = 0;
      var col = s.gold ? '232,206,132' : '246,241,231';   /* gold-soft / cream */
      if (s.glow){
        ctx.shadowColor = 'rgba(212,175,55,' + (a * 0.9) + ')';
        ctx.shadowBlur = 6;
      } else {
        ctx.shadowBlur = 0;
      }
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(' + col + ',' + a + ')';
      ctx.fill();
    }
    ctx.shadowBlur = 0;
  }

  var running = true;
  function loop(){
    if (running) paint(true);
    requestAnimationFrame(loop);
  }

  build();
  window.addEventListener('resize', function(){ build(); if (reduce) paint(false); });
  document.addEventListener('visibilitychange', function(){ running = !document.hidden; });

  if (reduce){
    paint(false);            // static field, no motion
  } else {
    requestAnimationFrame(loop);
  }
})();
