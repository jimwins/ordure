$(function() {
  $('[data-slug]').prepend($('<button type="button" class="btn btn-danger edit-slug" style="position: fixed; top: 2em; right: 2em; z-index: 300"><i class="fa fa-pencil"></i> Edit</button>'));

  if (typeof slug404 != 'undefined' && slug404) {
    $('#buttons').append($('<a class="edit-slug btn btn-success btn-lg" role="button" data-slug="' + slug404 + '">Create page &raquo;</a>'));
  }

  $('.edit-slug').on('click', function(ev) {
    $.ajax({ url: BASE + 'admin/page-editor.html', cache: false }).done(function (html) {
      var page_editor= $(html);

      page_editor.on('hidden.bs.modal', function() {
        $(this).remove();
      });

      var page= { id: 0, slug: '', title: '',
                  content: '', description: '', script: '',
                  rendered: '', error: '' };

      var rendered= $(ev.target).closest('[data-slug]');
      page.slug= rendered.data('slug');

      pageModel= ko.mapping.fromJS(page);

      $.getJSON(BASE + 'api/pageLoad?callback=?',
                { slug: page.slug })
        .done(function (data) {
          ko.mapping.fromJS(data, pageModel);
        })
        .fail(function (jqxhr, textStatus, error) {
          var data= $.parseJSON(jqxhr.responseText);
          pageModel.error(textStatus + ', ' + error + ': ' + data.text)
        });


      pageModel.savePage= function(place, ev) {
        $.ajax(BASE + 'api/pageSave',
               { type : 'POST', data : ko.mapping.toJS(pageModel) })
          .done(function (data) {
            $(place).closest('.modal').modal('hide');
            $('.rendered', rendered).empty().append(data.rendered);
          })
          .fail(function (jqxhr, textStatus, error) {
            var data= $.parseJSON(jqxhr.responseText);
            pageModel.error(textStatus + ', ' + error + ': ' + data.text)
          });
      }

      ko.applyBindings(pageModel, page_editor[0]);

      page_editor.appendTo($('body')).modal();
    });
  });
});
