function ajaxRequest(action, datalist, callback) {
    var request = false
    request = new XMLHttpRequest()
    request.open('POST', "./?page=ajax&action=" + action, true)
    request.setRequestHeader('Content-Type',
                             'application/x-www-form-urlencoded')
    var datastring = ''
    var first = true
    for(let [key, value] of datalist) {
        if(!first) {
            datastring += '&'
        }
        datastring += key + '=' + encodeURIComponent(value)
        first = false
    }
    request.send(datastring)

    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            var json_response = ''
            try {
                json_response = JSON.parse(request.responseText)
            } catch(error) {
                console.log(request.responseText)
            }
            callback(json_response)
        }
    }
}

function showResult(result) {
    hideMessage()
    var contents = document.querySelector('#contents')

    var render = function(fragment) {
        var temp = document.createElement('template')
        fragment = replace(fragment, [
            ['type', result.type],
            ['message', result.message]
        ])
        temp.innerHTML = fragment
        contents.append(temp.content.firstChild)
    }

    getFragment('message', render)
}

function hideMessage() {
    var contents = document.querySelector('#contents')
    var message = contents.querySelector('#message')
    if(message) {
        contents.removeChild(message)
    }
}

function reloadOrError(result) {
    if(result.type == 'success') {
        window.location.reload(true)
    } else {
        showResult(result)
    }
}

function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase()
}

function fixDuplicateInputNames(fields) {
    var names = {}
    for(var i = 0; i < fields.length; i++) {
        var name = fields[i].name
        if(name.endsWith('[]')) {
            continue
        }
        if(names.hasOwnProperty(name)) {
            fields[i].name = name + '[]'
            fields[names[name]].name = name + '[]'
        } else {
            names[name] = i
        }
    }
    return true
}

function dataListFromForm(form, filter = function(field) {return true}) {
    var out = []
    var fields = form.querySelectorAll('input,textarea')
    fixDuplicateInputNames(fields)
    for(var i = 0; i < fields.length; i++) {
        if(filter(fields[i])) {
            out.push([fields[i].name, fields[i].value])
        }
    }
    return out
}

function getFragment(name, callback) {
    var unpack = function(result) {
        if(result.type == 'success') {
            callback(result.message)
        } else {
            console.log(result);
        }
    }
    
    ajaxRequest('getfragment',
                [['fragment', name]],
                unpack)
}

function replace(fragment, replacements) {
    var work = fragment
    for(const [key, value] of replacements) {
        var regex = new RegExp('¤' + key + '¤', 'g')
        work = work.replace(regex, value)
    }
    return work
}

function returnProduct(event) {
    event.preventDefault()
    var form = event.currentTarget
    var handleResult = function(result) {
        showResult(result)
        form.serial.value = ''
        form.serial.select()
    }
    ajaxRequest('return', dataListFromForm(form), handleResult)
}

function checkoutProduct(event) {
    event.preventDefault()
    var form = event.currentTarget
    var user = form.user.value
    var product = form.product.value
    if(!user) {
        showResult({'type':'error',
                    'message':'Ingen låntagare vald.'})
        return
    }
    ajaxRequest('checkout', dataListFromForm(form), reloadOrError)
}

function showExtend(event) {
    event.preventDefault()
    var form = event.currentTarget
    var confirm = form.parentNode.querySelector('.renew_confirm')
    confirm.classList.remove('hidden')
    form.classList.add('hidden')
}

function extendLoan(event) {
    event.preventDefault()
    ajaxRequest('extend',
                dataListFromForm(event.currentTarget),
                reloadOrError)
}

function startInventory(event) {
    event.preventDefault()
    ajaxRequest('startinventory', [], reloadOrError)
}

function endInventory(event) {
    event.preventDefault()
    ajaxRequest('endinventory', [], reloadOrError)
}

function inventoryProduct(event) {
    event.preventDefault()
    ajaxRequest('inventoryproduct',
                dataListFromForm(event.currentTarget),
                reloadOrError)
}

function suggest(input, type) {
    var existing = []
    var capitalize = true
    switch(type) {
    default:
        return showResult({'type':'error',
                           'message':'Invalid suggestion type.'})
        break
    case 'field':
        var fieldlist = document.querySelectorAll('.info_item')
        for(var i = 0; i < fieldlist.length; i++) {
            existing.push(fieldlist[i].name)
        }
        break
    case 'tag':
        var taglist = document.querySelectorAll('#tags .tag > input')
        for(var i = 0; i < taglist.length; i++) {
            var tag = taglist[i].name
            existing.push(tag.toLowerCase())
        }
        break
    case 'template':
        break
    case 'user':
        capitalize = false
        break
    }
    var render = function(result) {
        var suggestlist = document.querySelector('#' + type + 'list')
        while(suggestlist.firstChild) {
            suggestlist.removeChild(suggestlist.firstChild)
        }
        var suggestions = result.message
        for(var i = 0; i < suggestions.length; i++) {
            var suggestion = suggestions[i].toLowerCase()
            if(existing.indexOf(suggestion) != -1) {
                continue
            }
            var next = document.createElement('option')
            if(capitalize) {
                next.value = ucfirst(suggestion)
            } else {
                next.value = suggestion
            }
            suggestlist.appendChild(next)
        }
    }
    ajaxRequest('suggest', [['type', type]], render)
}

function addField(event) {
    if(event.key && event.key != "Enter") {
        return
    }
    event.preventDefault()
    var tr = event.currentTarget.parentNode.parentNode
    var nameField = tr.querySelector('input')
    var form = nameField.form
    if(!nameField.value) {
        return showResult({'type': 'error',
                           'message': 'Fältet måste ha ett namn.'})
    }
    var key = nameField.value.toLowerCase()
    if(form.querySelector('input[name="' + key + '"]')) {
        return showResult(
            {'type': 'error',
             'message': 'Det finns redan ett fält med det namnet.'})
    }
    var name = ucfirst(key)
    var render = function(fragment) {
        var temp = document.createElement('template')
        fragment = replace(fragment, [
            ['name', name],
            ['key', key],
            ['value', '']
        ])
        temp.innerHTML = fragment
        temp = temp.content.firstChild
        var temptext = temp.firstChild.innerHTML
        var current = form.querySelector('#before_info').nextElementSibling
        var found = false
        while(!found) {
            if(current == tr || temptext < current.firstChild.innerHTML) {
                current.before(temp)
                found = true
            }
            current = current.nextElementSibling
        }
        nameField.value = ''
    }
    getFragment('info_item', render)
}

function escapeText(text) {
    return text
        .replace(/'/, '&#39;')
        .replace(/"/, '&#34;')
}

function addTag(event) {
    if(event.key && event.key != "Enter") {
        return suggest(event.currentTarget, 'tag')
    }
    event.preventDefault()
    var tr = event.currentTarget.parentNode.parentNode
    var field = tr.querySelector('.newtag')
    var tagname = escapeText(field.value)
    if(!tagname) {
        return showResult({'type': 'error',
                           'message': 'Taggen måste ha ett namn.'})
    }
    tagname = ucfirst(tagname)
    var tagElements = tr.querySelectorAll('.tag > input')
    for(var i = 0; i < tagElements.length; i++) {
        var oldtag = tagElements[i].name
        if(tagname.toLowerCase() == oldtag.toLowerCase()) {
            return showResult({'type': 'error',
                               'message': 'Det finns redan en sån tagg på artikeln.'})
        }
    }
    var render = function(fragment) {
        var temp = document.createElement('template')
        temp.innerHTML = replace(fragment, [['tag', tagname]])
        temp = temp.content.firstChild
        var tag = field.parentNode.firstChild
        var found = false
        while(!found) {
            if(tag == field || temp.innerHTML < tag.innerHTML) {
                tag.before(temp)
                found = true
            }
            tag = tag.nextElementSibling
        }
        field.value = ''
    }
    getFragment('tag', render)
}

function removeTag(event) {
    event.preventDefault()
    var tag = event.currentTarget.parentNode
    var parent = tag.parentNode
    parent.remove(tag)
}

function loadTemplate(event) {
    var form = event.currentTarget
    var template = form.template.value
    if(template === '') {
        return
    }
    var options = form.querySelector('#templatelist').options
    for(var i = 0; i < options.length; i++) {
        if(options[i].value == template) {
            return
        }
    }
    event.preventDefault()
    showResult({'type': 'error',
                'message': 'Det finns ingen mall med det namnet.'})
}

function saveTemplate(event) {
    event.preventDefault()
    var datalist = productDataList(document.querySelector('#productdata'))
    datalist.push(['template', event.currentTarget.form.template.value])
    ajaxRequest('savetemplate', datalist, showResult)
}

function deleteTemplate(event) {
    var input = event.currentTarget.form.template
    event.preventDefault()
    var render = function(result) {
        if(result.type == 'success') {
            input.value = ''
        }
        showResult(result)
    }
    ajaxRequest('deletetemplate',
                dataListFromForm(event.currentTarget.form),
                render)
}

function saveProduct(event) {
    event.preventDefault()
    var action = document.activeElement.id
    if(action == 'reset') {
        return window.location.reload(true)
    }
    var form = event.currentTarget
    var prodid = form.id.value
    if(prodid == '') {
        action = 'save'
    } else {
        action = 'update'
    }
    var datalist = productDataList(form)
    var render = function(result) {
        if(action == 'save' && result.type == 'success') {
            showResult(result)
            var inputs = form.querySelectorAll('input[type="text"]')
            for(var i = 0; i < inputs.length; i++) {
                inputs[i].value = '';
            }
        } else {
            reloadOrError(result)
        }
    }
    ajaxRequest('updateproduct', datalist, render)
}

function updateUser(event) {
    event.preventDefault()
    var action = document.activeElement.id
    if(action == 'reset') {
        return window.location.reload(true)
    }
    var form = event.currentTarget
    ajaxRequest('updateuser', dataListFromForm(form), reloadOrError)
}

function productDataList(form) {
    var filter = function(input) {
        var name = input.name
        if(name == 'new_key' || name == 'new_tag') {
            return false
        }
        return true
    }
    var datalist = dataListFromForm(form, filter)
    return datalist
}

function calendar(event) {
    var input = event.currentTarget
    if(!input.cal) {
        var cal = new dhtmlXCalendarObject(input.id)
        cal.hideTime()
        input.cal = cal
        cal.show()
    }
}

function discardProduct(event) {
    event.preventDefault()
    if(!window.confirm(
        'Är du säker på att du vill skrota artikeln? \n'
            + 'Den kommer fortsättningsvis kunna ses på Historik-sidan.')) {
        return
    }
    var form = event.currentTarget.parentNode
    var render = function(result) {
        if(result.type == 'success') {
            window.location.href = '?page=products'
        } else {
            showResult(result)
        }
    }
    ajaxRequest('discardproduct', dataListFromForm(form), render)
}

function searchInput(event) {
    if(event.key != "Enter") {
        return
    }
    var input = event.target
    var term = input.value.toLowerCase()
    if(term === '') {
        return
    }
    event.preventDefault()
    var terms = document.querySelector('#terms')
    var parts = escapeText(term).trim().split(':')
    var parsedTerm = 'Fritext: ' + parts[0]
    var key = 'fritext'
    var value = parts[0]
    if(parts.length > 1) {
        key = parts[0].trim()
        value = parts.slice(1).join(':').trim()
        parsedTerm = ucfirst(key) + ': ' + value
    }
    var render = function(fragment) {
        var temp = document.createElement('template')
        fragment = replace(fragment, [['term', parsedTerm],
                                      ['key', key],
                                      ['value', value]])
        temp.innerHTML = fragment
        terms.append(temp.content.firstChild)
        input.value = ''
    }
    getFragment('search_term', render)
}

function doSearch(event) {
    var form = document.querySelector('#search')
    var fields = form.querySelectorAll('input,textarea')
    fixDuplicateInputNames(fields)
}

function removeTerm(event) {
    event.preventDefault()
    var term = event.currentTarget.parentNode
    var parent = term.parentNode
    parent.remove(term)
}
