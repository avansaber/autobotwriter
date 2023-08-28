        jQuery(document).ready(function($) {
			console.log('aibot',aibot);
if($('#broaddescription').length)
$('#broaddescription').keyup(function() {
    
  var characterCount = $(this).val().length,
      current = $('#current'),
      maximum = $('#maximum'),
      theCount = $('#the-count');
    
  current.text(characterCount);
 
  
  if (characterCount ==500) {
    maximum.css('color', '#8f0001');
    current.css('color', '#8f0001');
    theCount.css('font-weight','bold');
  } else {
    maximum.css('color','#666');
    theCount.css('font-weight','normal');
  }
  
      
});
            
            function heartbeat(){
                console.log('beat!');
                $.ajax({
                    url:aibot.ajaxurl,
                    method:'POST',
                    data:{action:'aibot_heartbeat'},
                    success:function(){
                        setTimeout(heartbeat,5000);
                    },
                    error:function(){
                        setTimeout(heartbeat,5000);
                    }
                });
            }

            heartbeat();

            if($('#aibot_history').length)
            $('#aibot_history').dataTable({
                "bLengthChange": false,
                "order": [[0, 'desc']]
            });

            var hash = new URL(document.URL).hash;
            if(hash.trim()=='')
                hash = '#general'; 
                $('.nav-tab').removeClass('nav-tab-active');
            $('.ai-bot-writer-tab-content > *').css('display','none');
            $(hash).css('display','block');
                $(".nav-tab[href='"+hash+"']").addClass('nav-tab-active');

            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).attr('href');
                var url_ob = new URL(document.URL);
                url_ob.hash = tab;
                var new_url = url_ob.href;
                document.location.href = new_url;
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.ai-bot-writer-tab-content > div').hide();
                $(tab).show();
            });

            //Wizard code


            let wizardBar = document.querySelector('[data-wizard-bar]')
let btnPrevious = document.querySelector('[data-btn-previous]')
window.currentTab = 0;


function showTab(n) {
  let formTabs = document.querySelectorAll('[data-form-tab]');
  let wizardItem = document.querySelectorAll('[data-wizard-item]')
  formTabs[n].classList.add('active')
  wizardItem[n].classList.add('active')

  if (n == 0) {
    btnPrevious.style.display = "none";
  } else {
    btnPrevious.style.display = "block";
  }
}

async function getTitles(){
    $('#spinning').css('display','inline-block');
    var r = await $.ajax({
                    url: aibot.ajaxurl,
                    method:'POST',
                    data:{action:'aibot_get_titles',
                            broaddescription:$('#broaddescription').val(),
                            numberofposts:$('#numberofposts').val()},
                     

            });
    $('#spinning').css('display','none');
    return r;
}


async function submitWizardData(data){
    $('#spinning').css('display','inline-block');
    var r = await $.ajax({
                    url: aibot.ajaxurl,
                    method:'POST',
                    data:{action:'aibot_schedule_posts',
                            parameters:data,
                            broaddescription:$('#broaddescription').val()}
            });
    $('#spinning').css('display','none');
    if(r.indexOf('OK')!==-1){
        $('#aibot_success_message').css('display','block').addClass('notice notice-success');
        setTimeout(function(){window.location='admin.php?page=ai-bot-writer#history'; window.location.reload(); },5000);
    }else{
        console.log(data);
    }
}

async function nextPrev(n) {
  let formTabs = document.querySelectorAll('[data-form-tab]');
  if(n==1 && currentTab==4 || n==-1 && currentTab==0){
    return;
  }

  if (n == 1 && !validateForm()){  
    return false;
    } 

    if(currentTab==1 && n==1 && $('#generate').val()=='1'){
    $('[data-btn-next]').attr('disabled','disabled');
    var data = await getTitles();
    data = JSON.parse(data);
    $('[data-btn-next]').removeAttr('disabled');
    console.log('type',data);
    if( (typeof data).toLowerCase() == 'object'){
        data = data['data'].split("\n").map(function(v){ return v.replace(/^[0-9]+\.\s+/,'').replace(/\"/g,''); });
        data.forEach(function(v,k){
            $('#title'+k).val(v);
        });  
    }else{
        $('.feedback-titles').html(data);
        return;
    }
  }else if(currentTab==3 && n==1){
    var data = jQuery(document.forms.aibotwriter).serialize();
    $('[data-btn-next]').attr('disabled','disabled');
    submitWizardData(decodeURI(data)); 
    return;
  }else{ 
      if(currentTab==2 && n==1){
        $('[data-btn-next]').text($('#generatelabel').text());
      }else{
        $('[data-btn-next]').text($('#nextlabel').text());
      }
  }



  formTabs[currentTab].classList.remove('active')
  currentTab = currentTab + n;
  if(currentTab==3){
    loadPreview();
  }

  showTab(currentTab);
}

function validateForm() {
  let formTabs, formInputs, i, valid = true;
  formTabs = document.querySelectorAll('[data-form-tab]');
  formInputs = formTabs[currentTab].querySelectorAll('[data-form-input]');
  formSelects = formTabs[currentTab].querySelectorAll('[data-form-select]');
  formBroad = formTabs[currentTab].querySelectorAll('[data-form-broad]');
  formCount = formTabs[currentTab].querySelectorAll('[data-form-count]');
  formNumbers = formTabs[currentTab].querySelectorAll('[data-form-number]');
  let formItem = formTabs[currentTab].querySelectorAll('[data-form-item]');

  for (i = 0; i < formNumbers.length; i++) {
    if (formNumbers[i].value == "" || !(/^[0-9]{1,2}$/.test(formNumbers[i].value))) {
      formNumbers[i].className += " has-error";
      valid = false;
    } 
  }
     for (i = 0; i < formBroad.length; i++) {
    if ( $('#generate').val()==1 && (formBroad[i].value == "" || formBroad[i].value.length<30) ) {
      formBroad[i].className += " has-error";
      valid = false;
    } 
  }
    
     for (i = 0; i < formCount.length; i++) {
    if (formCount[i].value == "" || isNaN(parseInt(formCount[i].value.trim())) || parseInt(formCount[i].value.trim()) > 5 ) {
      formCount[i].className += " has-error";
      valid = false;
    } 
  }

  for (i = 0; i < formInputs.length; i++) {
    if (formInputs[i].value == "" && !(formInputs[i].id == 'broaddescription' && $('#generate').val()=='2')  ) {
      formInputs[i].className += " has-error";
      valid = false;
    } 
  }

  for (i = 0; i < formSelects.length; i++) {
    if (jQuery(formSelects[i]).val().trim() == "") {
      formSelects[i].className += " has-error";
      valid = false;
    } 
  }
  return valid;
}

function updateWizardBarWidth() {
  const activeWizards = document.querySelectorAll(".wizard-item.active");
  let wizardItem = document.querySelectorAll('[data-wizard-item]')
  const currentWidth = currentTab==0 ? 0 : (currentTab==1 ? 33.33 : (currentTab==2 ? 66.66 : 100));

  wizardBar.style.width = currentWidth + "%";
} 

function formatD(f){
    var d = new Date(f);
    return d.getFullYear()+'/'+(d.getMonth()+1).toString().padStart(2, '0')+'/'+d.getDate().toString().padStart(2, '0')
    +' '+d.getHours().toString().padStart(2, '0')+':'+d.getMinutes().toString().padStart(2, '0');
}

window.loadPreview = function(){
    var number = $('#numberofposts').val(),
        method = $('#generate').first().find('[value='+$('#generate').val()+']').text(),
        broad = $('#generate').val()=='1' ? 
                `<strong>`+$('#broaddescriptionlabel').text()+`:</strong> <span>`+$('#broaddescription').val()+`</span><br/>` 
                : "", info=''; 
        var l = $('#postsinfo .aibotpost-title').length; 
        for (var i = 0; i < l; i++) { 
            //if($('#title'+i).val().trim()=='') continue;
            var title = $('#title'+i).val()? $('#title'+i).val() : '',
                date = $('#date'+i).val()? formatD($('#date'+i).val()) : '',
                c = $('#category'+i).val()? 
                        $('.aibotpost-category').first().find('[value='+$('#category'+i).val()+']').text()
                         : '', 
                a = $('#author'+i).val()? 
                        $('.aibotpost-author').first().find('[value='+$('#author'+i).val()+']').text()
                         : '',
                t, 
                inc = $('#include'+i).val()? $('#include'+i).val() : '',
                e = $('#exclude'+i).val()? $('#exclude'+i).val() : '';
                values =  $('#tags'+i+" :selected").map(function(i, el) {
                    return $('.aibotpost-tags').first().find('[value='+$(el).val()+']').text();
}).get();
                if(values && values.length){
                    t = values.join(',');
                }else {
                    t = '';
                }


            info += `<tr>
            <td>`+title+`</td>
            <td>`+date+`</td>
            <td>`+c+`</td>
            <td>`+a+`</td>
            <td>`+t+`</td>
            <td>`+inc+`</td>
            <td>`+e+`</td>
            </tr>`;
        } 

        $('#numberconf').html(number);
        $('#methodconf').html(method);
        $('#broadconf').html(broad);
        $('#postsconfirmation').html(info);
};

if(wizardBar){
    showTab(currentTab);

    $('#generate').change(function(){
        if($(this).val()=='1'){
            $('#broaddescription,#the-count').css('display','block');
        }else{
            $('#broaddescription,#the-count').css('display','none');
        }
    });

    $('#numberofposts').change(function(){
        console.log('Data',aibot.categories,aibot.tags);
        var options = '',cats='',tagging='';
        if(!(/^[0-9]{1,2}$/.test($(this).val()))){ 
            console.log();
            return};

            for(k in aibot.users){
                options+= `<option value="${aibot.users[k].ID}">${aibot.users[k].data.display_name}</option>`;
            }
            for(k in aibot.categories){
                cats+= `<option value="${aibot.categories[k].cat_ID}">${aibot.categories[k].cat_name}</option>`;
            }
            for(k in aibot.tags){
                tagging+= `<option value="${aibot.tags[k].term_id}">${aibot.tags[k].name}</option>`;
            }

        $('#postsinfo').html('');
        for (var i = 0; i < parseInt($(this).val()); i++) {
            var base = $('#rowpoststemplate').html();
            base = base.replace(/INDEX/g,i);
            base = base.replace(/USERSDROPDOWN/g,options);
            base = base.replace(/CATS/g,cats);
            base = base.replace(/TAGGING/g,tagging);
            $('#postsinfo').append(base);

        }

        

            $('#postsinfo .aibotpost-date').each(function(k,v){
                if($(v).hasClass('flatpickr-input')) return;
                $(v).flatpickr({
    enableTime:true,
    altInput: true,
    altFormat: "m/d/Y H:i",
    dateFormat: "Y-m-d H:i",
});}); 
        if ($('select').data('select2')) {
   $('select').select2('destroy');
 }
            $('#postsinfo .aibotpost-category, #postsinfo .aibotpost-tags').each(function(k,v){
                if($(v).hasClass('select2-hidden-accessible')) return;
                $(v).select2();});
    });

document.querySelector('*').addEventListener('click', async function (event) {
  if (event.target.dataset.btnPrevious) {
    let wizardItem = document.querySelectorAll('[data-wizard-item]')
    wizardItem[currentTab].classList.remove('active')
    nextPrev(-1)
    updateWizardBarWidth()
  }
  if (event.target.dataset.btnNext) {
    await nextPrev(1)
    updateWizardBarWidth()
  }
});

}

// Settings Code

$('#openai_api_key').on('change',function(e){
        if($(this).val().trim()==''){
            return;
        }
        $.ajax({
            url: aibot.ajaxurl,
            method:'POST',
            data:{action:'aibot_validate_openai_key',key:$(this).val()},
            success:function(data){
                data = JSON.parse(data);
                if(data && data.data!=undefined){
                    var options = '';
                    data.data.forEach(function(v,k,arr){
                        options += `<option>${v.id}</option>`;
                        if(k==arr.length-1){
                            $('#ai_bot_writer_preferred_model').html(options);
                        }
                    });
                    $('#plugin-settings-form .feedback > *').css('display','none');
                    $('#plugin-settings-form #ai_bot_writer_preferred_model,#plugin-settings-form [type=submit]').removeAttr('disabled');
                }
                else{
                    $('#plugin-settings-form .feedback > *').css('display','none');
                    $('#plugin-settings-form .feedback .failure').css('display','block').addClass('notice notice-error');
                    $('#plugin-settings-form .errordetails').html(data);
                    $('#plugin-settings-form #ai_bot_writer_preferred_model,#plugin-settings-form [type=submit]').attr('disabled','disabled');
                }
            },
            error:function(e,e2){
                    $('#plugin-settings-form .feedback > *').css('display','none');
                    $('#plugin-settings-form .feedback .failure').css('display','block').addClass('notice notice-error');
                    $('#plugin-settings-form .errordetails').html(e2);
                    $('#plugin-settings-form #ai_bot_writer_preferred_model,#plugin-settings-form [type=submit]').attr('disabled','disabled');
            }
        });
        return false;
    });

    $('#plugin-settings-form').submit(function(){
        var d = $('#plugin-settings-form [type=submit]').attr('disabled');
        if(d=='disabled' || d ===true ){
            return false;
        }
        $.ajax({
            url: aibot.ajaxurl,
            method:'POST',
            data:{action:'aibot_save_settings',
                    openai_api_key:$('#openai_api_key').val(),
                    tokens:$('#openai_tokens').val(),
                    headings:$('#openai_headings').val(),
                    temperature:$('#openai_temperature').val(),
                    ai_bot_writer_preferred_model:$('#ai_bot_writer_preferred_model').val()},
            success:function(data){
                if(data.indexOf('OK')!=-1){
                    $('#plugin-settings-form .feedback > *').css('display','none');
                    $('#plugin-settings-form .feedback .success').css('display','block').addClass('notice notice-success');
                    setTimeout(function(){window.location.reload();},3000);
                }
                else{
                    $('#plugin-settings-form .feedback > *').css('display','none');
                    $('#plugin-settings-form .feedback .failure').css('display','block').addClass('notice notice-error');
                    $('#plugin-settings-form .errordetails').html(data);
                }
            },
            error:function(e,e2){

                    $('#plugin-settings-form .feedback > *').css('display','none');
                    $('#plugin-settings-form .feedback .failure').css('display','block').addClass('notice notice-error');
                    $('#plugin-settings-form .errordetails').html(e2);
            }
        });
        return false;
    });

});

