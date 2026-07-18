/* ===========================================================
   PRESTIGE — cursor glow trail
   A soft gold line/glow that follows and fades behind the cursor.
   Lightweight canvas, pointer-events:none, respects reduced-motion
   and disables itself on touch / coarse-pointer devices.
   =========================================================== */
(function(){
  var fine = window.matchMedia('(pointer:fine)').matches;
  var reduce = window.matchMedia('(prefers-reduced-motion:reduce)').matches;
  if(!fine || reduce) return;

  var canvas = document.createElement('canvas');
  canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:9999';
  document.body.appendChild(canvas);
  var ctx = canvas.getContext('2d');
  var dpr = Math.min(window.devicePixelRatio || 1, 2);

  function resize(){
    canvas.width = window.innerWidth * dpr;
    canvas.height = window.innerHeight * dpr;
    ctx.setTransform(dpr,0,0,dpr,0,0);
  }
  resize();
  window.addEventListener('resize', resize);

  var points = [];        // recent cursor positions
  var MAX = 18;           // trail length
  var mouse = {x:0, y:0, has:false};

  window.addEventListener('mousemove', function(e){
    mouse.x = e.clientX; mouse.y = e.clientY; mouse.has = true;
    points.push({x:e.clientX, y:e.clientY, life:1});
    if(points.length > MAX) points.shift();
  });

  document.addEventListener('mouseleave', function(){ mouse.has = false; });

  function draw(){
    ctx.clearRect(0,0,canvas.width,canvas.height);

    // fade + drop old points
    for(var i=0;i<points.length;i++){ points[i].life -= 0.06; }
    while(points.length && points[0].life <= 0) points.shift();

    if(points.length > 1){
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      for(var j=1;j<points.length;j++){
        var p0 = points[j-1], p1 = points[j];
        var t = j / points.length;          // newer segments => brighter/thicker
        var alpha = Math.max(0, p1.life) * t * 0.9;
        ctx.beginPath();
        ctx.moveTo(p0.x, p0.y);
        ctx.lineTo(p1.x, p1.y);
        ctx.lineWidth = 1 + t * 3.2;
        ctx.strokeStyle = 'rgba(232,206,132,' + alpha + ')';   /* --gold-soft */
        ctx.shadowColor = 'rgba(212,175,55,' + (alpha) + ')';   /* --gold */
        ctx.shadowBlur = 16 * t;
        ctx.stroke();
      }
      ctx.shadowBlur = 0;
    }

    // soft glow blob at the cursor head
    if(mouse.has){
      var g = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 26);
      g.addColorStop(0, 'rgba(244,228,166,0.30)');
      g.addColorStop(0.4, 'rgba(212,175,55,0.14)');
      g.addColorStop(1, 'rgba(212,175,55,0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(mouse.x, mouse.y, 26, 0, Math.PI*2);
      ctx.fill();
    }

    requestAnimationFrame(draw);
  }
  requestAnimationFrame(draw);
})();
