$(function() {
  $('[data-slug]').prepend($('<button type="button" class="btn btn-primary btn-xs edit-slug" style="float: right; position: relative; top: 0; right: 0"><span class="fa fa-pencil"></span></button>'));

  $('[data-department]').prepend($('<button type="button" class="btn btn-primary btn-xs edit-dept" style="float: right; position: relative; top: 0; right: 0"><span class="fa fa-pencil"></span></button>'));

  $('div[data-product]').prepend($('<button type="button" class="btn btn-primary btn-xs item-add" style="float: right; position: relative; top: 0; right: 0"><span class="fa fa-plus-circle"></span></button>'));
  $('div[data-product]').prepend($('<button type="button" class="btn btn-primary btn-xs edit-product" style="float: right; position: relative; top: 0; right: 0"><span class="fa fa-pencil"></span></button>'));

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
    $.ajax({ url: BASE + 'admin/dept-editor.html', cache: false }).done(function (html) {
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

  function productEdit(ev) {
    $.ajax({ url: BASE + 'admin/product-editor.html', cache: false }).done(function (html) {
      var page_editor= $(html);

      page_editor.on('hidden.bs.modal', function() {
        $(this).remove();
      });

      var product= $(ev.target).closest('[data-product]');
      var subdept= $(ev.target).closest('[data-parent]');

      var page= { id: product.data('product'),
                  department: subdept.data('parent'),
                  brand: 0,
                  slug: '', name: '', image: '',
                  variation_style: '',
                  description: '',
                  error: '',
                  departments: [], brands: [] };

      pageModel= ko.mapping.fromJS(page);

      if (page.id) {
        $.getJSON(BASE + 'api/productLoad?callback=?',
                  { id: page.id })
          .done(function (data) {
            ko.mapping.fromJS(data, pageModel);
          })
          .fail(function (jqxhr, textStatus, error) {
            var data= $.parseJSON(jqxhr.responseText);
            page.error(textStatus + ', ' + error + ': ' + data.text)
          });
      }

      $.getJSON(BASE + 'api/deptFind?callback=?',
                { levels: 2 })
        .done(function (data) {
          ko.mapping.fromJS({ departments: data }, pageModel);
          // make sure correct selection is made
          pageModel.department.valueHasMutated();
        });

      $.getJSON(BASE + 'api/brandFind?callback=?')
        .done(function (data) {
          ko.mapping.fromJS({ brands: data }, pageModel);
          // make sure correct selection is made
          pageModel.brand.valueHasMutated();
        });

      pageModel.saveProduct= function(place, ev) {
        var product= ko.mapping.toJS(pageModel);
        delete product.departments;

        $.ajax(BASE + 'api/productSave',
               { type : 'POST', data : product })
          .done(function (data) {
            $(place).closest('.modal').modal('hide');
          })
          .fail(function (jqxhr, textStatus, error) {
            var data= $.parseJSON(jqxhr.responseText);
            pageModel.error(textStatus + ', ' + error + ': ' + data.text)
          });
      }

      pageModel.selectedDepartment= ko.computed({
        read: function () {
          return this.department();
        },
        write: function (value) {
          if (typeof value != 'undefined' && value != '') {
            this.department(value);
          }
        },
        owner: pageModel
      }).extend({ notify: 'always' });

      pageModel.selectedBrand= ko.computed({
        read: function () {
          return this.brand();
        },
        write: function (value) {
          if (typeof value != 'undefined' && value != '') {
            this.brand(value);
          }
        },
        owner: pageModel
      }).extend({ notify: 'always' });

      pageModel.generateSlug= function(place, ev) {
        $.ajax(BASE + 'api/generateSlug',
               { type: 'POST', data: {
                 brand: pageModel.brand(), name: pageModel.name() }})
          .done(function (data) {
            pageModel.slug(data.slug);
          })
      }

      ko.applyBindings(pageModel, page_editor[0]);

      page_editor.appendTo($('body')).modal();
    });
  }

  $('.edit-product').on('click', productEdit);
  $('#product-add').on('click', productEdit);

  function itemEdit(ev) {
    $.ajax({ url: BASE + 'admin/item-editor.html', cache: false }).done(function (html) {
      var page_editor= $(html);

      page_editor.on('hidden.bs.modal', function() {
        $(this).remove();
      });

      var item= $(ev.target).closest('[data-item]');
      var product= $(ev.target).closest('[data-product]');

      var page= { id: item.data('item'),
                  product: product.data('product'),
                  code: '',
                  name: '',
                  short_name: '',
                  variation: '',
                  retail_price: 0.00,
                  thumbnail: '',
                  error: '',
                  departments: [], brands: [] };

      pageModel= ko.mapping.fromJS(page);

      if (page.id) {
        $.getJSON(BASE + 'api/itemLoad?callback=?',
                  { id: page.id })
          .done(function (data) {
            ko.mapping.fromJS(data, pageModel);
          })
          .fail(function (jqxhr, textStatus, error) {
            var data= $.parseJSON(jqxhr.responseText);
            page.error(textStatus + ', ' + error + ': ' + data.text)
          });
      }

      pageModel.loadFromScat= function(place, ev) {
        var code= pageModel.code();
        $.getJSON(BASE + 'api/itemLoadFromScat?callback=?',
                  { code: code })
          .done(function (data) {
            ko.mapping.fromJS(data, pageModel);
          })
          .fail(function (jqxhr, textStatus, error) {
            var data= $.parseJSON(jqxhr.responseText);
            page.error(textStatus + ', ' + error + ': ' + data.text)
          });
      }

      pageModel.saveItem= function(place, ev) {
        var item= ko.mapping.toJS(pageModel);

        // Don't even send over 'null' values (can't be encoded for POST)
        for (prop in item) {
          if (item[prop] === null)
            delete item[prop];
        }

        $.ajax(BASE + 'api/itemSave',
               { type: 'POST', data: item })
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

  $('.item-edit').on('click', itemEdit);
  $('.item-add').on('click', itemEdit);
});
