$(function() {
  $('[data-slug]').prepend($('<button type="button" class="btn btn-primary btn-xs edit-slug" style="float: right; position: relative; top: 0; right: 0"><span class="fa fa-pencil"></span></button>'));

  $('[data-department]').prepend($('<button type="button" class="btn btn-primary btn-xs edit-dept" style="float: right; position: relative; top: 0; right: 0"><span class="fa fa-pencil"></span></button>'));

  if (typeof slug404 != 'undefined' && slug404) {
    $('#buttons').append($('<a class="edit-slug btn btn-success btn-lg" role="button" data-slug="' + slug404 + '">Create page &raquo;</a>'));
  }

  $('.edit-slug').on('click', function(ev) {
    $.get(BASE + 'admin/page-editor.html').done(function (html) {
      var page_editor= $(html);

      page_editor.on('hidden.bs.modal', function() {
        $(this).remove();
      });

      var page= { id: 0, slug: '', title: '',
                  content: '', description: '',
                  rendered: '', error: '' };
      pageModel= ko.mapping.fromJS(page);

      var rendered= $(ev.target).closest('[data-slug]');

      var page_slug= rendered.data('slug');
      $.getJSON(BASE + 'api/pageLoad?callback=?',
                { slug: page_slug })
        .done(function (data) {
          ko.mapping.fromJS(data, pageModel);
        })
        .fail(function (jqxhr, textStatus, error) {
          var data= $.parseJSON(jqxhr.responseText);
          page.error(textStatus + ', ' + error + ': ' + data.text)
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

  $('.product-toggle').on('click', function(ev) {
    var product= $(ev.target).closest('[data-product]');

    $.getJSON(BASE + 'api/productToggle?callback=?',
              { product: product.data('product') })
      .done(function (data) {
        var cl= [ 'fa-eye', 'fa-eye text-muted', 'fa-eye-slash' ];
        $(ev.target).removeClass('fa-eye fa-eye-slash text-muted')
                    .addClass(cl[data.inactive]);
      })
      .fail(function (jqxhr, textStatus, error) {
        var data= $.parseJSON(jqxhr.responseText);
        // XXX page.error(textStatus + ', ' + error + ': ' + data.text)
      });
  });

  $('.item-toggle').on('click', function(ev) {
    var item= $(ev.target).closest('[data-item]');

    $.getJSON(BASE + 'api/itemToggle?callback=?',
              { item: item.data('item') })
      .done(function (data) {
        var cl= [ 'fa-eye', 'fa-eye text-muted', 'fa-eye-slash' ];
        $(ev.target).removeClass('fa-eye fa-eye-slash text-muted')
                    .addClass(cl[data.inactive]);
      })
      .fail(function (jqxhr, textStatus, error) {
        var data= $.parseJSON(jqxhr.responseText);
        // XXX page.error(textStatus + ', ' + error + ': ' + data.text)
      });
  });

  function deptEdit(ev) {
    $.get(BASE + 'admin/dept-editor.html').done(function (html) {
      var page_editor= $(html);

      page_editor.on('hidden.bs.modal', function() {
        $(this).remove();
      });

      var dept= $(ev.target).closest('[data-department]');
      var parent= $(ev.target).closest('[data-parent]');

      var page= { id: dept.data('department'),
                  parent: parent.data('parent'),
                  slug: '', name: '',
                  error: '', parents: [] };

      pageModel= ko.mapping.fromJS(page);

      $.getJSON(BASE + 'api/deptFind?callback=?',
                { levels: 1 })
        .done(function (data) {
          ko.mapping.fromJS({ parents: data }, pageModel);
          // make sure correct selection is made
          pageModel.parent.valueHasMutated();
        });

      if (page.id) {
        $.getJSON(BASE + 'api/deptLoad?callback=?',
                  { id: page.id })
          .done(function (data) {
            ko.mapping.fromJS(data, pageModel);
          })
          .fail(function (jqxhr, textStatus, error) {
            var data= $.parseJSON(jqxhr.responseText);
            page.error(textStatus + ', ' + error + ': ' + data.text)
          });
      }

      pageModel.saveDepartment= function(place, ev) {
        $.ajax(BASE + 'api/deptSave',
               { type : 'POST', data : ko.mapping.toJS(pageModel) })
          .done(function (data) {
            $(place).closest('.modal').modal('hide');
          })
          .fail(function (jqxhr, textStatus, error) {
            var data= $.parseJSON(jqxhr.responseText);
            pageModel.error(textStatus + ', ' + error + ': ' + data.text)
          });
      }

      ko.applyBindings(pageModel, page_editor[0]);

      page_editor.appendTo($('body')).modal();
    });
  }

  $('#dept-add').on('click', deptEdit);
  $('.edit-dept').on('click', deptEdit);

});
