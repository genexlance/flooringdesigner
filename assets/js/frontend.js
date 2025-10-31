(function(){
  if (typeof window === 'undefined') return;
  const config = window.nfdFrontendConfig || {};
  function createEl(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (value === null || value === undefined) return;
      if (key === 'class') {
        el.className = value;
      } else if (key.startsWith('data-')) {
        el.setAttribute(key, value);
      } else {
        el[key] = value;
      }
    });
    (Array.isArray(children) ? children : [children]).forEach(child => {
      if (child === null || child === undefined) return;
      if (Array.isArray(child)) {
        child.forEach(nested => nested && el.appendChild(typeof nested === 'string' ? document.createTextNode(nested) : nested));
        return;
      }
      if (typeof child === 'string') {
        el.appendChild(document.createTextNode(child));
      } else {
        el.appendChild(child);
      }
    });
    return el;
  }
  function buildField(label, children, extraClass = '') {
    const nodes = Array.isArray(children) ? children : [children];
    return createEl('div', { class: 'nfd-custom-field' + (extraClass ? ' ' + extraClass : '') }, [
      createEl('label', { class: 'nfd-label' }, label),
      ...nodes
    ]);
  }
  function createSpinner(){
    return createEl('span', { class: 'nfd-spinner', ariaHidden: 'true' });
  }
  function createApp(container) {
    const state = {
      roomFile: null,
      roomPreview: '',
      referenceFile: null,
      referencePreview: '',
      presets: Array.isArray(config.presets) ? config.presets : [],
      materials: Array.isArray(config.materials) ? config.materials : [],
      selectedSample: null,
      loading: false,
      error: '',
      processedImage: '',
      processedUrl: '',
      sliderValue: 50,
      sliderDragActive: false,
      sliderDragPointerId: null,
      custom: {
        materialId: '',
        materialName: '',
        dimension: '',
        customDimension: '',
        customDimensionMode: false,
        style: '',
        prompt: ''
      },
      customIsOpen: !config.presets || !config.presets.length
    };
    const elements = {};
    function sliderHasResult(){return Boolean(state.processedImage||state.processedUrl);}
    function normalizeSliderValue(value){
      const numeric = typeof value === 'number'?value:parseFloat(value);
      if (!Number.isFinite(numeric)) return state.sliderValue;
      const clamped = Math.max(0, Math.min(100, numeric));
      return Math.round(clamped*1000)/1000;
    }
    function releaseSliderPointerCapture(pointerId,options={}){
      const wrapper = elements.beforeAfter;
      if (pointerId!==undefined&&pointerId!==null&&state.sliderDragPointerId!==null&&pointerId!==state.sliderDragPointerId){return;}
      if (wrapper&&state.sliderDragPointerId!==null&&typeof wrapper.releasePointerCapture==='function'){
        try{wrapper.releasePointerCapture(state.sliderDragPointerId);}catch(error){}
      }
      state.sliderDragPointerId=null;
      state.sliderDragActive=false;
      if(!options.skipSync){syncSliderUI();}
    }
    function syncSliderUI(options={}){
      const hasResult=Object.prototype.hasOwnProperty.call(options,'hasResult')?options.hasResult:sliderHasResult();
      const slider=elements.slider;
      if(slider){
        const sanitized=(Math.round(state.sliderValue*1000)/1000).toString();
        if(slider.value!==sanitized){slider.value=sanitized;}
        slider.disabled=!hasResult;
      }
      if(elements.afterWrapper){
        const appliedValue=hasResult?state.sliderValue:0;
        elements.afterWrapper.style.width=appliedValue+'%';
      }
      if(elements.sliderIndicator){
        const indicatorPosition=hasResult?state.sliderValue:50;
        elements.sliderIndicator.style.left=indicatorPosition+'%';
      }
      if(elements.beforeAfter){
        elements.beforeAfter.classList.toggle('nfd-before-after--ready',hasResult);
        elements.beforeAfter.classList.toggle('nfd-before-after--dragging',state.sliderDragActive&&hasResult);
      }
    }
    function setSliderValue(value,options={}){
      const next=normalizeSliderValue(value);
      if(!options.force&&Math.abs(next-state.sliderValue)<0.001)return state.sliderValue;
      state.sliderValue=next;
      syncSliderUI();
      return state.sliderValue;
    }
    function updateSliderFromPointer(event){
      if(!elements.beforeAfter)return;
      const rect=elements.beforeAfter.getBoundingClientRect();
      if(!rect.width)return;
      const raw=((event.clientX-rect.left)/rect.width)*100;
      setSliderValue(raw,{force:true});
    }
    function handleSliderPointerDown(event){
      if(!sliderHasResult())return;
      if(!event.isPrimary)return;
      if(event.pointerType==='mouse'&&event.button!==0)return;
      event.preventDefault();
      const wrapper=elements.beforeAfter;
      if(!wrapper)return;
      state.sliderDragActive=true;
      state.sliderDragPointerId=event.pointerId;
      if(typeof wrapper.setPointerCapture==='function'){
        try{wrapper.setPointerCapture(event.pointerId);}catch(error){}
      }
      if(elements.slider&&typeof elements.slider.focus==='function'){
        try{elements.slider.focus({preventScroll:true});}catch(error){}
      }
      updateSliderFromPointer(event);
      syncSliderUI();
    }
    function handleSliderPointerMove(event){
      if(!state.sliderDragActive)return;
      if(state.sliderDragPointerId!==event.pointerId)return;
      event.preventDefault();
      updateSliderFromPointer(event);
    }
    function handleSliderPointerUp(event){
      if(state.sliderDragPointerId===null)return;
      if(event.pointerId!==state.sliderDragPointerId)return;
      event.preventDefault();
      releaseSliderPointerCapture(event.pointerId);
    }
    function setState(patch, renderOptions = {}){Object.assign(state,patch);render(renderOptions);}
    function updateCustom(patch, renderOptions = {}){setState({custom:Object.assign({},state.custom,patch)}, renderOptions);}
    function findMaterial(id){return id?state.materials.find(material=>material.id===id)||null:null;}
    function resetProcessing(){
      releaseSliderPointerCapture();
      setState({processedImage:'',processedUrl:'',sliderValue:50});
    }
    function onRoomChange(event){
      const file = event.target.files[0];
      if (!file) return;
      if (!config.allowedMimeTypes.includes(file.type)) {
        alert('Unsupported file type. Use JPEG, PNG, or WEBP.');
        event.target.value = '';
        return;
      }
      if (file.size > config.maxUploadBytes) {
        alert('File exceeds 10MB limit.');
        event.target.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = () => setState({ roomFile: file, roomPreview: reader.result });
      reader.readAsDataURL(file);
      resetProcessing();
    }
    function onReferenceChange(event){
      const file = event.target.files[0];
      if (!file) return;
      if (!config.allowedMimeTypes.includes(file.type)) {
        alert('Unsupported reference image type.');
        event.target.value = '';
        return;
      }
      if (file.size > config.maxUploadBytes) {
        alert('Reference image exceeds 10MB limit.');
        event.target.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = () => setState({ referenceFile: file, referencePreview: reader.result }, { skipPresets: true });
      reader.readAsDataURL(file);
    }
    function selectPreset(preset){
      setState({ selectedSample: preset, error: '', customIsOpen: preset.id === 'custom' });
      resetProcessing();
    }
    function buildPresetGrid(){
      const grid = createEl('div', { class: 'nfd-preset-grid' });
      if (state.presets.length) {
        state.presets.forEach(preset => {
          const card = createEl('button', { class: 'nfd-card', type: 'button' }, [
            preset.thumbnail ? createEl('img', { src: preset.thumbnail, alt: preset.title }) : null,
            createEl('div', { class: 'nfd-card-body' }, [
              createEl('strong', {}, preset.title),
              preset.style ? createEl('span', { class: 'nfd-card-meta' }, preset.style) : null,
            ])
          ]);
          card.addEventListener('click', () => selectPreset({
            id: preset.id,
            name: preset.title,
            prompt: preset.description || '',
            dimension: preset.dimension || '',
            style: preset.style || ''
          }));
          if (state.selectedSample && state.selectedSample.id === preset.id) {
            card.classList.add('nfd-card-active');
          }
          grid.appendChild(card);
        });
      }
      grid.appendChild(createCustomCard());
      return grid;
    }
    function createCustomCard(){
      const wrapper = createEl('div', { class: 'nfd-card-custom' });
      const header = createEl('button', { class: 'nfd-card', type: 'button' }, [
        createEl('div', { class: 'nfd-card-body' }, [
          createEl('div', {}, [
            createEl('strong', {}, 'Custom Flooring✨'),
            createEl('span', { class: 'nfd-card-meta' }, 'Choose a material and fine-tune the look'),
          ]),
          createEl('span', { class: 'nfd-arrow' })
        ])
      ]);
      const body = createEl('div', { class: 'nfd-custom-body' });
      const materials = state.materials;
      const selectedMaterial = findMaterial(state.custom.materialId);
      if (!materials.length) {
        body.appendChild(createEl('p', { class: 'nfd-hint' }, 'Add materials under Nano-Floor -> Flooring Materials to unlock guided options.'));
      }
      const materialSelect = createEl('select', { class: 'nfd-input' });
      materialSelect.appendChild(createEl('option', { value: '' }, 'Select material type (optional)'));
      materials.forEach(material => materialSelect.appendChild(createEl('option', { value: material.id }, material.name)));
      materialSelect.value = state.custom.materialId;
      materialSelect.addEventListener('change', event => {
        const id = event.target.value;
        const material = findMaterial(id);
        updateCustom({
          materialId: id,
          materialName: material ? material.name : '',
          dimension: '',
          customDimension: '',
          customDimensionMode: material ? (material.dimensions || []).length === 0 : false,
          style: ''
        }, { skipPresets: true });

        // Update dimension select
        if (elements.dimensionSelect) {
          elements.dimensionSelect.innerHTML = '';
          elements.dimensionSelect.appendChild(createEl('option', { value: '' }, 'Select dimensions'));
          if (material && material.dimensions.length > 0) {
            material.dimensions.forEach(dimension => elements.dimensionSelect.appendChild(createEl('option', { value: dimension }, dimension)));
            elements.dimensionSelect.appendChild(createEl('option', { value: '__custom__' }, 'Custom size...'));
            elements.dimensionSelect.parentElement.parentElement.style.display = 'block'; // Show dimension section
          } else {
            elements.dimensionSelect.parentElement.parentElement.style.display = 'none'; // Hide dimension section
          }
          elements.dimensionSelect.value = state.custom.customDimensionMode ? '__custom__' : state.custom.dimension;
        }

        // Update style select
        if (elements.styleSelect) {
          elements.styleSelect.innerHTML = '';
          elements.styleSelect.appendChild(createEl('option', { value: '' }, 'Select style'));
          if (material && material.styles.length > 0) {
            material.styles.forEach(style => elements.styleSelect.appendChild(createEl('option', { value: style }, style)));
            elements.styleSelect.parentElement.parentElement.style.display = 'block'; // Show style section
          } else {
            elements.styleSelect.parentElement.parentElement.style.display = 'none'; // Hide style section
          }
          elements.styleSelect.value = state.custom.style;
        }

        // Update custom dimension visibility
        if (elements.dimensionCustomWrapper) {
          const hasDimensions = material && material.dimensions.length > 0;
          const customDimensionVisible = !hasDimensions || state.custom.customDimensionMode;
          elements.dimensionCustomWrapper.style.display = customDimensionVisible ? 'block' : 'none';
        }
      });
      const materialSection = buildField('Material type (optional)', materialSelect);
      // Always create dimension select
      const dimensionSelect = createEl('select', { class: 'nfd-input' });
      dimensionSelect.appendChild(createEl('option', { value: '' }, 'Select dimensions'));
      if (selectedMaterial && selectedMaterial.dimensions.length > 0) {
        selectedMaterial.dimensions.forEach(dimension => dimensionSelect.appendChild(createEl('option', { value: dimension }, dimension)));
        dimensionSelect.appendChild(createEl('option', { value: '__custom__' }, 'Custom size...'));
      }
      dimensionSelect.value = state.custom.customDimensionMode ? '__custom__' : state.custom.dimension;
      dimensionSelect.addEventListener('change', event => {
        const value = event.target.value;
        if (value === '__custom__') {
          updateCustom({ dimension: '', customDimensionMode: true, customDimension: state.custom.customDimension }, { skipPresets: true });
        } else if (value === '') {
          updateCustom({ dimension: '', customDimensionMode: false, customDimension: '' }, { skipPresets: true });
        } else {
          updateCustom({ dimension: value, customDimensionMode: false, customDimension: '' }, { skipPresets: true });
        }
      });
      elements.dimensionSelect = dimensionSelect;

      const hasDimensions = selectedMaterial && selectedMaterial.dimensions.length > 0;
      const customDimensionVisible = !hasDimensions || state.custom.customDimensionMode;
      const dimensionPlaceholder = hasDimensions ? 'Enter custom size (e.g., 6 in x 48 in)' : 'Enter dimensions (e.g., 6 in x 48 in)';
      const dimensionCustomInput = createEl('input', { class: 'nfd-input', placeholder: dimensionPlaceholder, value: state.custom.customDimension });
      dimensionCustomInput.addEventListener('input', event => { updateCustom({ customDimension: event.target.value }, { skipPresets: true }); });
      const dimensionCustomWrapper = createEl('div', { class: 'nfd-custom-dimension', style: customDimensionVisible ? '' : 'display:none;' }, [dimensionCustomInput]);
      if (hasDimensions) {
        dimensionCustomWrapper.appendChild(createEl('span', { class: 'nfd-hint' }, 'Choose "Custom size..." to type your own.'));
      }
      elements.dimensionCustomWrapper = dimensionCustomWrapper;

      const dimensionChildren = [dimensionSelect, dimensionCustomWrapper];
      const dimensionSection = buildField('Dimensions (optional)', dimensionChildren);
      dimensionSection.style.display = hasDimensions ? 'block' : 'none'; // Initially hide if no dimensions
      // Always create style select
      const styleSelect = createEl('select', { class: 'nfd-input' });
      styleSelect.appendChild(createEl('option', { value: '' }, 'Select style'));
      if (selectedMaterial && selectedMaterial.styles.length > 0) {
        selectedMaterial.styles.forEach(style => styleSelect.appendChild(createEl('option', { value: style }, style)));
      }
      styleSelect.value = state.custom.style;
      styleSelect.addEventListener('change', event => updateCustom({ style: event.target.value }, { skipPresets: true }));
      elements.styleSelect = styleSelect;

      const styleSection = buildField('Style', styleSelect);
      styleSection.style.display = (selectedMaterial && selectedMaterial.styles.length > 0) ? 'block' : 'none'; // Initially hide if no styles
      const promptInput = createEl('textarea', { class: 'nfd-input', rows: 4, placeholder: 'Describe the flooring look (max 240 characters)', value: state.custom.prompt });
      promptInput.maxLength = 240;
      promptInput.addEventListener('input', event => { updateCustom({ prompt: event.target.value }, { skipPresets: true }); });
      const promptSection = buildField('Description', promptInput, 'nfd-custom-field-full');
      const referenceInput = createEl('input', { type: 'file', accept: config.allowedMimeTypes.join(','), class: 'nfd-input-file' });
      referenceInput.addEventListener('change', onReferenceChange);
      const referenceField = buildField('Floor reference image (optional)', referenceInput, 'nfd-ref-group nfd-custom-field-full');
      referenceField.appendChild(createEl('span', { class: 'nfd-hint' }, 'JPEG, PNG, or WEBP up to 10MB.'));
      const actions = createEl('div', { class: 'nfd-custom-actions nfd-custom-field nfd-custom-field-full' });
      const useButton = createEl('button', { class: 'nfd-button', type: 'button' }, 'Use Custom Selection');
      useButton.addEventListener('click', () => {
        const material = findMaterial(state.custom.materialId);
        const dimensionValue = (state.custom.customDimensionMode || !(material && material.dimensions.length))
          ? (state.custom.customDimension || '').trim()
          : (state.custom.dimension || '').trim();
        const promptText = (state.custom.prompt || '').trim();
        if (!material && !promptText && !state.referenceFile) {
          setState({ error: 'Add a material, description, or reference image before generating.' });
          return;
        }
        if (promptText.length > 240) {
          setState({ error: 'Please shorten the description (max 240 characters).' });
          return;
        }
        const detailParts = [];
        if (material) detailParts.push('Material: ' + material.name);
        if (dimensionValue) detailParts.push('Dimensions: ' + dimensionValue);
        if (state.custom.style) detailParts.push('Style: ' + state.custom.style);
        const composedDetails = detailParts.join('; ');
        const finalPromptPieces = [];
        if (composedDetails) finalPromptPieces.push(composedDetails);
        if (promptText) finalPromptPieces.push(promptText);
        const composedPrompt = finalPromptPieces.join('. ');
        if (composedPrompt.length > 300) {
          setState({ error: 'Please shorten the selection details or description.' });
          return;
        }
        selectPreset({
          id: 'custom',
          name: 'Custom Flooring',
          prompt: composedPrompt,
          materialId: state.custom.materialId,
          materialName: material ? material.name : '',
          dimension: dimensionValue,
          style: state.custom.style,
        });
      });
      const clearRef = createEl('button', { class: 'nfd-button-secondary', type: 'button' }, 'Clear reference');
      clearRef.addEventListener('click', () => {
        setState({ referenceFile: null, referencePreview: '' });
        referenceInput.value = '';
      });
      actions.appendChild(useButton);
      actions.appendChild(clearRef);
      body.appendChild(materialSection);
      body.appendChild(dimensionSection);
      body.appendChild(styleSection);
      body.appendChild(promptSection);
      body.appendChild(referenceField);
      body.appendChild(actions);
      header.addEventListener('click', () => {
        setState({ customIsOpen: !state.customIsOpen });
      });
      if (state.customIsOpen) {
        wrapper.classList.add('nfd-custom-open');
      }
      if (state.selectedSample && state.selectedSample.id === 'custom') {
        wrapper.classList.add('nfd-card-active');
      }
      wrapper.appendChild(header);
      wrapper.appendChild(body);
      return wrapper;
    }
    function renderPresets(){
      if (!elements.presetArea) return;
      elements.presetArea.innerHTML = '';
      elements.presetArea.appendChild(buildPresetGrid());
    }
    function renderPreviews(){
      const beforeImg = elements.beforeImage;
      const afterImg = elements.afterImage;
      if (state.roomPreview) {
        beforeImg.src = state.roomPreview;
        beforeImg.alt = 'Uploaded room image';
      }
      const hasResult = sliderHasResult();
      if (hasResult) {
        const processedSrc = state.processedImage || state.processedUrl;
        if (processedSrc) {
          afterImg.src = processedSrc;
        }
        afterImg.alt = 'Processed flooring image';
      } else {
        afterImg.removeAttribute('src');
        afterImg.alt = '';
        releaseSliderPointerCapture(undefined,{skipSync:true});
      }
      syncSliderUI({hasResult});
    }
    function renderReferencePreview(){
      if (!elements.referencePreview) return;
      elements.referencePreview.innerHTML = '';
      if (state.referencePreview) {
        elements.referencePreview.appendChild(createEl('img', { src: state.referencePreview, alt: 'Reference flooring preview' }));
      }
    }
    function renderStatus(){
      elements.errorBox.textContent = state.error || '';
      elements.errorBox.style.display = state.error ? 'block' : 'none';
      if (state.loading) {
        elements.overlay.classList.add('nfd-overlay-visible');
        elements.generateBtn.classList.add('nfd-loading');
      } else {
        elements.overlay.classList.remove('nfd-overlay-visible');
        elements.generateBtn.classList.remove('nfd-loading');
      }
      elements.generateBtn.disabled = state.loading;
      const hasResult = Boolean(state.processedImage || state.processedUrl);
      if (elements.downloadBtn) elements.downloadBtn.style.display = hasResult ? 'inline-flex' : 'none';
      if (elements.shareBtn) elements.shareBtn.style.display = hasResult ? 'inline-flex' : 'none';
    }
    function render(options = {}){
      if (!options.skipPresets) {
        renderPresets();
      }
      renderPreviews();
      renderReferencePreview();
      renderStatus();
      elements.generateBtn.textContent = state.loading ? config.strings.processing : config.strings.generateAction;
    }
    function buildLayout(){
      container.innerHTML = '';
      container.classList.add('nfd-shell');
      const grid = createEl('div', { class: 'nfd-grid' });
      const main = createEl('section', { class: 'nfd-main' });
      const sidebar = createEl('aside', { class: 'nfd-sidebar' });
      const uploadSection = createEl('div', { class: 'nfd-card-panel' }, [
        createEl('h2', {}, config.strings.uploadPrompt),
        (() => {
          const label = createEl('label', { class: 'nfd-upload' });
          const input = createEl('input', { type: 'file', accept: config.allowedMimeTypes.join(','), class: 'nfd-input-file' });
          elements.roomInput = input;
          input.addEventListener('change', onRoomChange);
          label.appendChild(input);
          label.appendChild(createEl('span', { class: 'nfd-upload-hint' }, 'JPEG, PNG, or WEBP up to 10MB.'));
          return label;
        })(),
        createEl('div', { class: 'nfd-result' }, [
          (() => {
            const wrapper = createEl('div', { class: 'nfd-before-after' });
            wrapper.addEventListener('pointerdown', handleSliderPointerDown);
            wrapper.addEventListener('pointermove', handleSliderPointerMove);
            wrapper.addEventListener('pointerup', handleSliderPointerUp);
            wrapper.addEventListener('pointercancel', handleSliderPointerUp);
            wrapper.addEventListener('pointerleave', handleSliderPointerUp);
            const before = createEl('img', { class: 'nfd-image-before', alt: '' });
            const afterWrap = createEl('div', { class: 'nfd-after-wrap' });
            const after = createEl('img', { class: 'nfd-image-after', alt: '' });
            const indicator = createEl('div', { class: 'nfd-slider-indicator', ariaHidden: 'true' });
            const slider = createEl('input', { type: 'range', min: 0, max: 100, step: 0.1, value: state.sliderValue, class: 'nfd-slider', disabled: true, ariaLabel: 'Adjust flooring overlay' });
            slider.addEventListener('input', () => {
              setSliderValue(slider.value);
            });
            elements.beforeImage = before;
            elements.afterImage = after;
            elements.afterWrapper = afterWrap;
            elements.beforeAfter = wrapper;
            elements.sliderIndicator = indicator;
            elements.slider = slider;
            afterWrap.appendChild(after);
            wrapper.appendChild(before);
            wrapper.appendChild(afterWrap);
            wrapper.appendChild(indicator);
            wrapper.appendChild(slider);
            return wrapper;
          })(),
          (() => {
            const toolbar = createEl('div', { class: 'nfd-toolbar' });
            const generate = createEl('button', { class: 'nfd-button-primary', type: 'button' }, config.strings.generateAction);
            generate.addEventListener('click', onGenerate);
            elements.generateBtn = generate;
            const download = createEl('a', { class: 'nfd-button-secondary', href: '#', download: 'nano-floor-result.png' }, 'Download');
            download.addEventListener('click', onDownload);
            elements.downloadBtn = download;
            const share = createEl('button', { class: 'nfd-button', type: 'button' }, 'Share');
            share.addEventListener('click', onShare);
            elements.shareBtn = share;
            toolbar.appendChild(generate);
            toolbar.appendChild(download);
            toolbar.appendChild(share);
            return toolbar;
          })(),
          (() => {
            const box = createEl('div', { class: 'nfd-error' });
            elements.errorBox = box;
            return box;
          })()
        ])
      ]);
      const overlay = createEl('div', { class: 'nfd-overlay' }, createEl('div', { class: 'nfd-overlay-inner' }, [createSpinner(), createEl('span', { class: 'nfd-loading-text' }, config.strings.processing)]));
      elements.overlay = overlay;
      uploadSection.appendChild(overlay);
      main.appendChild(uploadSection);
      sidebar.appendChild(createEl('h2', {}, config.strings.selectPrompt));
      const presetArea = createEl('div', { class: 'nfd-presets' });
      elements.presetArea = presetArea;
      sidebar.appendChild(presetArea);
      const referencePreview = createEl('div', { class: 'nfd-reference-preview' });
      elements.referencePreview = referencePreview;
      sidebar.appendChild(referencePreview);
      grid.appendChild(main);
      grid.appendChild(sidebar);
      container.appendChild(grid);
    }
    async function onGenerate(){
      if (!state.roomFile) {
        setState({ error: 'Upload a room image before generating.' });
        return;
      }
      if (!state.selectedSample) {
        setState({ error: 'Select a flooring preset or configure a custom option.' });
        return;
      }
      const form = new FormData();
      form.append('roomImage', state.roomFile);
      if (state.referenceFile) form.append('referenceImage', state.referenceFile);
      form.append('sample', JSON.stringify(state.selectedSample));
      setState({ loading: true, error: '' });
      try {
        const res = await fetch(config.restUrl + 'process', {
          method: 'POST',
          headers: { 'X-WP-Nonce': config.restNonce },
          body: form
        });
        const data = await res.json();
        if (!res.ok) {
          throw new Error(data && data.message ? data.message : config.strings.errorGeneric);
        }
        setState({
          processedImage: data.processed_base64 || '',
          processedUrl: data.processed_url || '',
          loading: false,
          error: ''
        });
        if (data.processed_base64) {
          elements.downloadBtn.href = data.processed_base64;
        } else if (data.processed_url) {
          elements.downloadBtn.href = data.processed_url;
        }
      } catch (err) {
        setState({ loading: false, error: err.message || config.strings.errorGeneric });
      }
    }
    function onDownload(event){
      if (!state.processedImage && !state.processedUrl) {
        event.preventDefault();
      }
    }
    async function onShare(){
      if (!state.processedImage && !state.processedUrl) return;
      const url = state.processedUrl || state.processedImage;
      const shareData = {
        title: 'Nano-Floor Designer Result',
        text: 'Check out this flooring visualization from Nano-Floor Designer.',
        url: state.processedUrl || undefined
      };
      if (navigator.share && state.processedUrl) {
        try {
          await navigator.share(shareData);
          return;
        } catch (err) {
          console.error(err);
        }
      }
      try {
        await navigator.clipboard.writeText(url);
        alert('Link copied to clipboard.');
      } catch (err) {
        console.error(err);
        alert('Copy failed. Use download instead.');
      }
    }
    buildLayout();
    render();
  }
  function init(){
    document.querySelectorAll('.nfd-app').forEach(createApp);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
