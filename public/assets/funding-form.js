/* ===========================================================
   PRESTIGE — Funding Eligibility Quiz logic
   9 steps. Single-select auto-advances; multi-select &
   contact steps use the OK / Submit button. Enter & letter
   keys supported. Back button, animated progress bar.
   =========================================================== */
(function(){
  var form = document.getElementById('fqForm');
  if(!form) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('.fq-step'));
  var QCOUNT = steps.filter(function(s){ return s.dataset.type !== 'done'; }).length; // 9
  var fill = document.getElementById('fqFill');
  var cat = document.getElementById('fqCat');
  var count = document.getElementById('fqCount');
  var foot = document.getElementById('fqFoot');
  var backBtn = document.getElementById('fqBack');
  var okBtn = document.getElementById('fqOk');
  var enterHint = foot.querySelector('.fq-enter');
  var answers = {};
  var idx = 0;

  function current(){ return steps[idx]; }

  function render(){
    steps.forEach(function(s,i){ s.classList.toggle('active', i === idx); });
    var s = current();
    var type = s.dataset.type;
    var isDone = type === 'done';

    cat.textContent = s.dataset.cat;
    var num = Math.min(idx + 1, QCOUNT);
    count.textContent = isDone ? (QCOUNT + ' / ' + QCOUNT) : (num + ' / ' + QCOUNT);
    fill.style.width = (isDone ? 100 : (num / QCOUNT) * 100) + '%';

    foot.style.display = isDone ? 'none' : 'flex';
    backBtn.style.visibility = idx === 0 ? 'hidden' : 'visible';

    // single-select advances on click, so hide the Press-Enter/OK affordance there
    var manual = (type === 'multi' || type === 'contact');
    enterHint.style.display = manual ? 'inline' : 'none';
    okBtn.style.display = (type === 'single') ? 'none' : 'inline-flex';
    okBtn.textContent = '';
    okBtn.insertAdjacentHTML('beforeend', type === 'contact' ? 'Submit &amp; Get My Strategy Call &rarr;' : 'OK &rarr;');

    if(type === 'multi') syncMulti();
  }

  function goNext(){
    if(idx < steps.length - 1){ idx++; render(); scrollIntoView(); }
  }
  function goBack(){
    if(idx > 0){ idx--; render(); scrollIntoView(); }
  }
  function scrollIntoView(){
    var top = document.querySelector('.fq-section').getBoundingClientRect().top + window.pageYOffset - 90;
    window.scrollTo({ top: top, behavior: 'smooth' });
  }

  // ----- option clicks -----
  form.addEventListener('click', function(e){
    var opt = e.target.closest('.fq-opt');
    if(!opt) return;
    var step = opt.closest('.fq-step');
    var type = step.dataset.type;

    if(type === 'multi'){
      var exclusive = opt.dataset.exclusive === 'true';
      if(exclusive){
        step.querySelectorAll('.fq-opt').forEach(function(o){ if(o!==opt) o.classList.remove('selected'); });
        opt.classList.toggle('selected');
      } else {
        var ex = step.querySelector('.fq-opt[data-exclusive="true"]');
        if(ex) ex.classList.remove('selected');
        opt.classList.toggle('selected');
      }
      syncMulti();
    } else {
      // single — mark + auto-advance
      step.querySelectorAll('.fq-opt').forEach(function(o){ o.classList.remove('selected'); });
      opt.classList.add('selected');
      answers[step.dataset.cat] = opt.dataset.val;
      setTimeout(goNext, 260);
    }
  });

  function syncMulti(){
    var step = current();
    var chosen = step.querySelectorAll('.fq-opt.selected');
    okBtn.disabled = chosen.length === 0;
    var vals = [];
    chosen.forEach(function(o){ vals.push(o.dataset.val); });
    answers[step.dataset.cat] = vals.join(', ');
  }

  // ----- OK / Submit -----
  okBtn.addEventListener('click', function(){
    var s = current();
    var type = s.dataset.type;
    if(type === 'multi'){
      if(!s.querySelectorAll('.fq-opt.selected').length) return;
      goNext();
    } else if(type === 'contact'){
      var inputs = s.querySelectorAll('input');
      var ok = true;
      inputs.forEach(function(i){ if(!i.checkValidity()){ ok = false; } });
      if(!ok){ inputs.forEach(function(i){ if(!i.checkValidity()) i.reportValidity(); }); return; }
      inputs.forEach(function(i){ answers[i.name] = i.value; });
      // Send the full set of answers to the dashboard.
      try{
        fetch('/api/leads/funding', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ answers: answers })
        });
      }catch(err){}
      goNext();
    } else {
      goNext();
    }
  });

  backBtn.addEventListener('click', goBack);

  // ----- keyboard -----
  document.addEventListener('keydown', function(e){
    var s = current();
    if(!s || s.dataset.type === 'done') return;
    // don't hijack typing in inputs
    var typing = /input|textarea|select/i.test(document.activeElement.tagName);

    if(e.key === 'Enter'){
      if(s.dataset.type === 'single'){
        var sel = s.querySelector('.fq-opt.selected'); if(sel){ e.preventDefault(); goNext(); }
      } else { e.preventDefault(); okBtn.click(); }
      return;
    }
    if(typing) return;
    // letter shortcuts A–E
    var k = e.key.toUpperCase();
    if(/^[A-E]$/.test(k)){
      var opts = s.querySelectorAll('.fq-opt');
      var n = k.charCodeAt(0) - 65;
      if(opts[n]) opts[n].click();
    }
  });

  render();
})();
