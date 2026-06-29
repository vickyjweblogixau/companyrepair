(function () {
  const zone    = document.getElementById('crsUploadZone');
  const empty   = document.getElementById('crsUploadEmpty');
  const grid    = document.getElementById('crsPreviewGrid');
  const trigger = document.getElementById('crsUploadTrigger');
  const status  = document.getElementById('crsUploadStatus');
  const counter = document.getElementById('crsUploadCounter');
  // The real CF7 input
  const realInput = document.getElementById('crs-upload');

  const MAX    = 3;
  const MAX_MB = 5;
  let files    = [];

  if (!zone || !realInput) return;

  function fmt(bytes) {
    return bytes < 1024 * 1024
      ? (bytes / 1024).toFixed(0) + ' KB'
      : (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function setStatus(msg, type) {
    status.textContent = msg;
    status.className   = 'crs-upload-status' + (type ? ' ' + type : '');
  }

  // Sync files[] back into the real CF7 input via DataTransfer
  function syncToRealInput() {
    const dt = new DataTransfer();
    files.forEach(f => dt.items.add(f));
    realInput.files = dt.files;
  }

  function render() {
    grid.innerHTML = '';

    if (files.length === 0) {
      empty.style.display = '';
      grid.style.display  = 'none';
      zone.classList.remove('has-files');
      counter.textContent = '';
      return;
    }

    empty.style.display = 'none';
    grid.style.display  = '';
    zone.classList.add('has-files');

    files.forEach(function (f, i) {
      const cell = document.createElement('div');
      cell.className = 'crs-preview-cell';

      if (f.type === 'application/pdf') {
        cell.innerHTML =
          '<div class="crs-pdf-cell">' +
            '<span class="pdf-icon"><i class="fa-solid fa-file-pdf"></i></span>' +
            '<span class="pdf-name">' + f.name + '</span>' +
            '<span class="pdf-size">' + fmt(f.size) + '</span>' +
          '</div>';
      } else {
        const url = URL.createObjectURL(f);
        const img = document.createElement('img');
        img.src = url;
        img.alt = f.name;
        img.loading = 'lazy';
        cell.appendChild(img);
      }

      const btn = document.createElement('button');
      btn.type      = 'button';
      btn.className = 'crs-remove-btn';
      btn.setAttribute('aria-label', 'Remove ' + f.name);
      btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      btn.onclick = function () {
        files.splice(i, 1);
        syncToRealInput();
        render();
        setStatus('');
      };
      cell.appendChild(btn);
      grid.appendChild(cell);
    });

    // "Add more" slot
    if (files.length < MAX) {
      const add = document.createElement('div');
      add.className        = 'crs-add-more';
      add.setAttribute('role', 'button');
      add.setAttribute('tabindex', '0');
      add.setAttribute('aria-label', 'Add another file');
      add.innerHTML = '<i class="fa-solid fa-plus"></i><span>Add more</span>';
      add.onclick   = openPicker;
      add.onkeydown = function (e) {
        if (e.key === 'Enter' || e.key === ' ') openPicker();
      };
      grid.appendChild(add);
    }

    counter.textContent = files.length + ' of ' + MAX + ' files selected';
  }

  function validate(f) {
    const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    if (!allowed.includes(f.type)) {
      return 'Only JPG, PNG or PDF files are allowed.';
    }
    if (f.size > MAX_MB * 1024 * 1024) {
      return f.name + ' exceeds the 5 MB limit.';
    }
    return null;
  }

  function addFiles(incoming) {
    let added = 0;
    for (const f of Array.from(incoming)) {
      if (files.length >= MAX) {
        setStatus('Maximum 3 files allowed.', 'error');
        break;
      }
      const err = validate(f);
      if (err) { setStatus(err, 'error'); continue; }
      if (files.find(x => x.name === f.name && x.size === f.size)) continue;
      files.push(f);
      added++;
    }
    syncToRealInput();
    if (added > 0) setStatus(added + ' file' + (added > 1 ? 's' : '') + ' added', 'success');
    render();
  }

  function openPicker() {
    const input    = document.createElement('input');
    input.type     = 'file';
    input.accept   = 'image/jpeg,image/jpg,image/png,application/pdf';
    input.multiple = files.length < MAX - 1;
    input.onchange = function () { addFiles(input.files); };
    input.click();
  }

  trigger.onclick = openPicker;
  zone.onclick = function (e) {
    if (e.target === zone || e.target === empty) openPicker();
  };

  zone.addEventListener('dragover', function (e) {
    e.preventDefault();
    zone.classList.add('drag-over');
  });
  zone.addEventListener('dragleave', function () {
    zone.classList.remove('drag-over');
  });
  zone.addEventListener('drop', function (e) {
    e.preventDefault();
    zone.classList.remove('drag-over');
    addFiles(e.dataTransfer.files);
  });

  render();
})();