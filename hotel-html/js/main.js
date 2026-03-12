// ============================================================
//  main.js — Hôtel Luxe — Core JS & Three.js Hero
// ============================================================

// ── Three.js Hero Animation ──────────────────────────────────
(function initThreeHero() {
  const canvas = document.getElementById('canvas-bg');
  if (!canvas) return;

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);

  const scene  = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
  camera.position.z = 5;

  // ── Particules flottantes ──
  const particleCount = 280;
  const positions     = new Float32Array(particleCount * 3);
  const sizes         = new Float32Array(particleCount);

  for (let i = 0; i < particleCount; i++) {
    positions[i * 3]     = (Math.random() - 0.5) * 16;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 9;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 6;
    sizes[i]             = Math.random() * 2.5 + 0.5;
  }

  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  geo.setAttribute('size',     new THREE.BufferAttribute(sizes, 1));

  const mat = new THREE.PointsMaterial({
    color:       0xC9A84C,
    size:        0.04,
    transparent: true,
    opacity:     0.65,
    sizeAttenuation: true,
  });

  const particles = new THREE.Points(geo, mat);
  scene.add(particles);

  // ── Lignes dorées ──
  function createLine(start, end) {
    const points = [new THREE.Vector3(...start), new THREE.Vector3(...end)];
    const g = new THREE.BufferGeometry().setFromPoints(points);
    const m = new THREE.LineBasicMaterial({ color: 0xC9A84C, transparent: true, opacity: 0.08 });
    return new THREE.Line(g, m);
  }

  for (let i = 0; i < 18; i++) {
    scene.add(createLine(
      [(Math.random()-0.5)*12, (Math.random()-0.5)*7, (Math.random()-0.5)*3],
      [(Math.random()-0.5)*12, (Math.random()-0.5)*7, (Math.random()-0.5)*3]
    ));
  }

  // ── Sphère centrale ──
  const sphereGeo = new THREE.IcosahedronGeometry(1.2, 1);
  const sphereMat = new THREE.MeshBasicMaterial({
    color: 0xC9A84C,
    wireframe: true,
    transparent: true,
    opacity: 0.06,
  });
  const sphere = new THREE.Mesh(sphereGeo, sphereMat);
  sphere.position.set(3, -0.5, -1);
  scene.add(sphere);

  // ── Animation loop ──
  let mouseX = 0, mouseY = 0;
  document.addEventListener('mousemove', e => {
    mouseX = (e.clientX / window.innerWidth  - 0.5) * 0.8;
    mouseY = (e.clientY / window.innerHeight - 0.5) * 0.5;
  });

  let t = 0;
  function animate() {
    requestAnimationFrame(animate);
    t += 0.004;

    particles.rotation.y  = mouseX * 0.3 + t * 0.05;
    particles.rotation.x  = mouseY * 0.2 + Math.sin(t * 0.3) * 0.05;
    sphere.rotation.y     += 0.003;
    sphere.rotation.x     += 0.002;
    camera.position.x     += (mouseX * 0.4 - camera.position.x) * 0.05;
    camera.position.y     += (-mouseY * 0.3 - camera.position.y) * 0.05;

    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });
})();

// ── Navbar scroll behavior ───────────────────────────────────
(function initNavbar() {
  const navbar = document.getElementById('navbar');
  if (!navbar) return;

  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 80);
  });

  // Mobile toggle
  const toggle = document.querySelector('.navbar-toggle');
  const links  = document.querySelector('.navbar-links');
  if (toggle && links) {
    toggle.addEventListener('click', () => links.classList.toggle('open'));
  }
})();

// ── Custom cursor ────────────────────────────────────────────
(function initCursor() {
  const cur   = document.querySelector('.curseur');
  const anneau= document.querySelector('.curseur-anneau');
  if (!cur || !anneau) return;

  let cx = 0, cy = 0, ax = 0, ay = 0;

  document.addEventListener('mousemove', e => {
    cx = e.clientX; cy = e.clientY;
    cur.style.left   = cx - 5  + 'px';
    cur.style.top    = cy - 5  + 'px';
  });

  function loop() {
    ax += (cx - ax) * 0.12;
    ay += (cy - ay) * 0.12;
    anneau.style.left = ax - 18 + 'px';
    anneau.style.top  = ay - 18 + 'px';
    requestAnimationFrame(loop);
  }
  loop();
})();

// ── Scroll reveal ────────────────────────────────────────────
(function initReveal() {
  const els = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
  const obs  = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });
  els.forEach(el => obs.observe(el));
})();

// ── Compteur animé ───────────────────────────────────────────
(function initCounters() {
  const counters = document.querySelectorAll('[data-count]');
  const obs      = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      const el     = e.target;
      const target = parseInt(el.dataset.count);
      const suffix = el.dataset.suffix || '';
      let start = 0;
      const step = target / 60;
      const timer = setInterval(() => {
        start += step;
        el.textContent = Math.min(Math.ceil(start), target) + suffix;
        if (start >= target) clearInterval(timer);
      }, 20);
      obs.unobserve(el);
    });
  }, { threshold: 0.5 });
  counters.forEach(c => obs.observe(c));
})();

// ── Toast notification ───────────────────────────────────────
function afficherToast(message, type = 'succes') {
  let toast = document.getElementById('toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.className = `toast ${type}`;
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 4000);
}

window.afficherToast = afficherToast;

// ── Galerie lightbox simple ──────────────────────────────────
(function initGalerie() {
  document.querySelectorAll('.galerie-item').forEach(item => {
    item.addEventListener('click', () => {
      const img = item.querySelector('img');
      if (!img) return;
      const lb = document.createElement('div');
      lb.style.cssText = `position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out`;
      const i = document.createElement('img');
      i.src = img.src;
      i.style.cssText = 'max-width:90%;max-height:90vh;object-fit:contain;border:1px solid rgba(201,168,76,0.3)';
      lb.appendChild(i);
      lb.addEventListener('click', () => lb.remove());
      document.body.appendChild(lb);
    });
  });
})();
