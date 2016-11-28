###
  Front-end handlebars parser
  @author: K.Perov <fe3dback@yandex.ru>
###

class MoreDraw

  partials = null
  dataCache = null

  constructor: ->

    #noinspection JSUnresolvedVariable
    if (__handlebars_server_partials?)
      @partials = __handlebars_server_partials;

    #noinspection JSUnresolvedVariable
    if (__handlebars_server_data?)
      @dataCache = __handlebars_server_data;

  render: (name, data) ->

    name = name.replace('/', "__")

    $el = $("#hb-#{name}");
    return "" unless $el?

    tpl = Handlebars.compile($el.html())
    return tpl(data, {
      'partials': @partials
    })

  getData: (name, index) ->

    return unless @dataCache?

    data = @dataCache[name]?[index]
    return unless data?
    return data

window.BitrixHandlebars = new BitrixHandlebars()