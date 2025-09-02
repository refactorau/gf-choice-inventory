(function($){
  function isChoiceBased(field){
    var t = window.GetInputType ? GetInputType(field) : field.type;
    return ['radio','checkbox','select'].indexOf(t) !== -1;
  }
  function readLimitsMap(field){
    var raw = field.gfci_limits_map;
    if (!raw) return {};
    if (typeof raw === 'string'){ try { return JSON.parse(raw)||{}; } catch(e){ return {}; } }
    if (typeof raw === 'object') return raw;
    return {};
  }
  function writeLimitsMap(field, map){
    var json = '{}'; try { json = JSON.stringify(map||{}); } catch(e){}
    SetFieldProperty('gfci_limits_map', json);
    var f = GetSelectedField() || field; if (f) f.gfci_limits_map = json;
  }
  function setChoiceProp(field, idx, prop, value){
    if (typeof window.SetFieldChoiceProperty === 'function'){
      window.SetFieldChoiceProperty(idx, prop, value);
    } else {
      var f = GetSelectedField() || field; if (!f) return;
      f.choices = f.choices || []; f.choices[idx] = f.choices[idx] || {};
      f.choices[idx][prop] = value;
      SetFieldProperty('choices', f.choices);
    }
  }
  function renderTable(field){
    var $tbody = $('#gfci_rows'); $tbody.empty();
    if (!field || !Array.isArray(field.choices) || field.choices.length === 0){
      var msg = (window.gfciL10n && gfciL10n.noChoices) || 'No choices found. Add choices and they will appear here automatically.';
      $tbody.append('<tr><td colspan="2" class="gfci_muted">'+ msg +'</td></tr>');
      return;
    }
    var limitsMap = readLimitsMap(field);
    field.choices.forEach(function(choice, idx){
      var label = (choice.text ?? '').toString();
      var value = (choice.value ?? '').toString();
      var live  = (choice.gfci_limit != null && choice.gfci_limit !== '') ? String(choice.gfci_limit) : '';
      var limit = live !== '' ? live : (limitsMap[value] != null ? String(limitsMap[value]) : '');
      var $tr = $('<tr/>');
      $tr.append($('<td/>').text(label));
      var $limit = $('<input type="number" min="-999999" step="1" placeholder="" />').val(limit);
      $limit.on('input', function(){
        setChoiceProp(field, idx, 'gfci_limit', this.value);
        var map = readLimitsMap(GetSelectedField() || field);
        if (this.value === '' || this.value === null) { delete map[value]; } else { map[value] = this.value; }
        writeLimitsMap(field, map);
      });
      $tr.append($('<td/>').append($limit));
      $tbody.append($tr);
    });
  }
  var watchInterval = null, lastSig = '';
  function sig(choices){ try{ return JSON.stringify((choices||[]).map(function(c){return [c.text,c.value]})); }catch(e){ return String(Math.random()); } }
  function startWatcher(field){
    stopWatcher(); if (!field) return;
    lastSig = sig(field.choices);
    watchInterval = setInterval(function(){
      var f = GetSelectedField(); if (!f) return;
      var s = sig(f.choices);
      if (s !== lastSig){ lastSig = s; renderTable(f); }
    }, 500);
  }
  function stopWatcher(){ if (watchInterval){ clearInterval(watchInterval); watchInterval = null; } }
  $(document).on('gform_load_field_settings', function(_e, field){
    var show = isChoiceBased(field);
    $('.gfci_block')[ show ? 'show' : 'hide' ]();
    $('#gfci_enabled').prop('checked', !!field.gfci_enabled);
    $('.gfci_wrap')[ field.gfci_enabled ? 'show' : 'hide' ]();
    $('#gfci_message').val(field.gfci_message || '');
    $('#gfci_allow').prop('checked', !!field.gfci_allow_soldout_submissions);
    if (show){ renderTable(field); startWatcher(field); } else { stopWatcher(); }
  });
  $(window).on('unload', stopWatcher);
})(jQuery);
