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
    var contents = document.querySelector('#contents')
    var oldmessage = contents.querySelector('#message')
    
    if(oldmessage) {
        contents.removeChild(oldmessage)
    }

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

function hideMessage(event) {
    var contents = document.querySelector('#contents')
    var message = contents.querySelector('#message')
    contents.removeChild(message)
}

function reloadOrError(result) {
    if(result.type == 'success') {
        window.location.reload(true)
    } else {
        showResult(result)
    }
}

function dataListFromForm(form, filter = function(field) {return true}) {
    var out = []
    var fields = form.querySelectorAll('input,textarea')
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
    ajaxRequest('return', dataListFromForm(form), showResult)
}

function checkoutProduct(event) {
    event.preventDefault()
    var form = event.currentTarget
    var user = form.user.value
    var product = form.product.value
    if(!user) {
        showResult({'type':'error',
                    'message':'Ingen användare vald.'})
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
    console.log(dataListFromForm(event.currentTarget))
    ajaxRequest('inventoryproduct',
                dataListFromForm(event.currentTarget),
                reloadOrError)
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
        showResult({'type': 'error',
                    'message': 'Fältet måste ha ett namn.'})
        return
    }
    var key = nameField.value.toLowerCase()
    if(form.querySelector('input[name="' + key + '"]')) {
        showResult({'type': 'error',
                    'message': 'Det finns redan ett fält med det namnet.'})
        return
    }
    var name = key.charAt(0).toUpperCase() + key.slice(1)
    var render = function(fragment) {
        var temp = document.createElement('template')
        fragment = replace(fragment, [
            ['name', name],
            ['key', key],
            ['value', '']
        ])
        temp.innerHTML = fragment
        tr.before(temp.content.firstChild)
        nameField.value = ''
    }
    getFragment('info_item', render)
}

function addTag(event) {
    if(event.key && event.key != "Enter") {
        return
    }
    event.preventDefault()
    var tr = event.currentTarget.parentNode.parentNode
    var field = tr.querySelector('.newtag')
    var tagname = field.value
    if(!tagname) {
        showResult({'type': 'error',
                    'message': 'Taggen måste ha ett namn.'})
        return
    }
    if(tagname.indexOf(',') > -1) {
        showResult({'type': 'error',
                    'message': 'Taggar får inte innehålla kommatecken.'})
        return
    }
    tagname = tagname.charAt(0).toUpperCase() + tagname.slice(1)
    var tagElements = tr.querySelectorAll('.tag')
    for(var i = 0; i < tagElements.length; i++) {
        var oldtag = tagElements[i].dataset['name']
        if(tagname.toLowerCase() == oldtag.toLowerCase()) {
            showResult({'type': 'error',
                        'message': 'Det finns redan en sån tagg på artikeln.'})
            return
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
    var tag = event.currentTarget
    var parent = tag.parentNode
    parent.remove(tag)
}

function saveProduct(event) {
    event.preventDefault()
    var action = event.explicitOriginalTarget.id
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
    var filter = function(input) {
        var name = input.name
        if(name == 'new_key' || name == 'new_tag') {
            return false
        }
        return true
    }
    var datalist = dataListFromForm(form, filter)
    var tagElements = form.querySelectorAll('.tag')
    var tags = []
    for(var i = 0; i < tagElements.length; i++) {
        tags.push(tagElements[i].dataset['name'])
    }
    datalist.push(['tags', tags])
    var render = function(result) {
        showResult(result)
        if(action == 'save' && result.type == 'success') {
            var reset = function(fragment) {
                var temp = document.createElement('template')
                temp.innerHTML = replace(fragment, [['id', ''],
                                                    ['name', ''],
                                                    ['invoice', ''],
                                                    ['serial', ''],
                                                    ['info', ''],
                                                    ['tags', '']])
                temp = temp.content.firstChild
                form.replaceWith(temp)
            }
            getFragment('product_details', reset)
        } else {
            return reloadOrError(result)
        }
    }
    ajaxRequest('updateproduct', datalist, render)
}

function updateUser(event) {
    event.preventDefault()
    var action = event.explicitOriginalTarget.id
    if(action == 'reset') {
        return window.location.reload(true)
    }
    var form = event.currentTarget
    ajaxRequest('updateuser', dataListFromForm(form), reloadOrError)
}
