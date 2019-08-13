
// give $ back
var jQuery_A = jQuery.noConflict();

var advocado = (function($, A) { 

    var POPUP_TITLE = 'Create a new Advocado account',
        POPUP_FEATURES = { 
            width: 400,
            height: 550,
            left: window.screen.width/2 - 400/2,
            top: window.screen.height/2 - 550/2,
            location: 'no',
            toolbar: 0,
            menubar: 'no'
        };

    function toggleErrorMsgOff() { 
        $('.advoc-errors').addClass('hide');
    }

    function displayError(type, errorMsg)  {
        var div = null;
        if (type === 'register') { 
            div = $('#advoc-register-container .advoc-errors');
        } else if (type === 'login') { 
            div = $('#advoc-login-container .advoc-errors');
        } 

        if (div) { 
            div.html(errorMsg);
            div.removeClass('hide');
        }
    }

    // legacy, currently unused
    function advocadoDashboard() { 

        var deferred = $.Deferred(function(dfd) { 
            timeout = setTimeout(dfd.reject, 4000);
        });

        var container = document.getElementById('advoc-dashboard-container-dash');
        try {
            var socket = new easyXDM.Socket({
                remote: '<?php echo $this->advocadoDashboardUrl(); ?>',
                container: container,
                onMessage: function(message, origin) { 
                    // it needs to get confirmation that it is on the dashboard
                    // before it displays the whole thing.
                    console.log('advocadoDashboard():message = ' + message);
                    if (message) { 
                        var pageObj = $.parseJSON(message);
                        if (pageObj['page'] === 'dashboard') { 
                            // display correctly
                            $(container).css({ display: 'block', height: '80%', width: '100%'})
                                .find('iframe').css({ height: '100%', width: '100%'});
                            clearTimeout(timeout);
                            // just in case, we hide the loginContainer again
                            $('#advoc-login-container').css('display', 'none');
                            deferred.resolve();
                        }
                    }
                }
            });
        } catch(e) { 
            console.log('advocadoDashboard: caught a socket exception = (' + 
                e.name + ') ' + e.message);
        }

        return deferred.promise();
    }

    // legacy, currently unused
    function loginFail(xhr, txtStatus, errorThrown) {
        var errorDiv = $('#advoc-errors');
        if (xhr.status < 500) { 
            if (xhr.responseText) { 
                try { 
                    var jsonText = $.parseJSON(xhr.responseText);
                } catch (e) { 
                    // error parsing json
                    // means it was not an unforeseen error
                    errorDiv.html('Invalid username or password.');
                }
            }
        } else { 
            errorDiv.html(
                'Sorry, there seems to be a problem logging you in.' +
                'Please try again later.'
            );
        }
    }

    function loginState(username, password) { 

        //$('.page-state').hide();
        $('#advoc-register-container').addClass('animated fadeOutRightBig hide');
        $('#advoc-login-container').removeClass('hide');
        $('#advoc-login-container').addClass('animated fadeInLeftBig');

        if (typeof username !== 'undefined' && 
            typeof password !== 'undefined') {

            // enter the username and password and submit
            $('#advoc-login-form input#advoc-username').val(username);
            $('#advoc-login-form input#advoc-password').val(password);
            $('#advoc-login-form').submit();
       }
    }

    // --------------------------------
    // GETTING URLS FROM THE PAGE
    // --------------------------------
    
    function _magentoAuthUrl()  {
        return $('#advoc-magento-auth-url').val();
    }

    function _advocadoDashboardLoginUrl() { 
        return $('#advoc-dashboard-login-url').val();
    }

    function _magentoRegisterUrl() { 
        return $('#advoc-magento-register-url').val();
    }

    function loginAdvocadoDashboard(username, password) { 

        var deferred = $.Deferred();
        var container = document.getElementById('advoc-dashboard-container-login');
        var socket = new easyXDM.Socket({
            remote: _advocadoDashboardLoginUrl(),
            container: container,
            onMessage: function(message, origin) { 

                console.log('loginAdvocadoDasbboard:onMessage:message = ' + message);
                var state = $.parseJSON(message);
                if (state && state.hasOwnProperty('page')) { 
                    if (state['page'] === 'login') {
                        console.log('login page, sending credentials');
                        // hide the login form
                        $('#advoc-login-container').css('display', 'none');
                        $(container).css({ height: '80%', width: '100%'});
                        $(container).find('iframe').eq(0).css({ height: '80%', width: '100%'});
                        // send the credentials
                        socket.postMessage(JSON.stringify({
                           username: username,
                           password: password
                        }));
                    } else { 
                        console.log('aww... rejected');
                    }
                    deferred.resolve();
                } else { 
                    console.log('received message - invalid: ' + message);
                    deferred.reject();
                }
            }
        });
        return deferred.promise();
    }

    function loginToAdvocado(username, password) { 
        return function() { 
            return loginAdvocadoDashboard(username, password);
        };
    }

    // gets the credentials from the backend
    function getCredentialsState(username, password, store) { 

        var d = $.Deferred();
        $.ajax(_magentoAuthUrl(), 
            {
                type: 'POST',
                dataType: 'json',
                data:{
                    username: username,
                    password: password,
                    website_store_group: store 
                }
        })
        .done(function(data, txtStatus, xhr) {
            console.log('admin ajax: done!');
            console.log('admin ajax: data = ' + data);
            d.resolve();
        })
        .fail(function(xhr, txtStatus, errThrown) {
            console.log('admin ajax: fail!');
            console.log('admin ajax: txtStatus: ' + txtStatus);
            console.log('admin ajax: code: ' + xhr.status);
            console.log('admin ajax: responseText ' + xhr.responseText);
            d.reject(xhr, txtStatus, errThrown);
        });;
        return d.promise();
    }

    function signUpState($form) { 

        var button = $form.find('input[type=submit]');
        button.val('Creating account...');

        var params = $form.serialize();
        $.ajax(_magentoRegisterUrl(), {
            type: 'POST',
            data: params
        })
        .done(function(data) { 

            // if 200, we start the login flow
            if (typeof data === 'string')  {
                try { 
                    data = $.parseJSON(data);
                } catch(e) { 
                    // error parsing, could be anything, just return
                    displayError('register', 'An error occurred. Advocado has been notified and will get back to you as soon as we can.');
                    button.val('Create An Account');
                    return;
                }
            }

            var store = $('#advoc-registration-form ' +
                'select.advoc-website-store-group option:selected').val();

            getCredentialsState(data.data['u'], data.data['p'], store)
            .then(
                //loginAdvocadoDashboard,
                loginToAdvocado(data.data['u'], data.data['p']),
                // what about failure? need to handle this
                function() { 
                    displayError('register', 
                        'We could not connect to the Advocado service. Please try again at a later time.');
                }
            )
            .then(
                function() { 
                    $('.page-state').addClass('hide');
                }
            );
        })
        .fail(function(xhr, txtStatus, errThrown) {
            if (xhr.status === 403) { 
                displayError('register', 'An account with this email address already exists. Please log in');
            } else { 
                displayError('register', 'There was an error signing up. You might have used an invalid email address.');
            }
            button.val('Create An Account');
        });
    }

    function loginFormSubmit(e) { 

        e.preventDefault();
        e.returnValue = false;

        // first check that it's through magento;
        //loginAdminAjax()
        var username = $('#advoc-login-form input[name=username]').val(),
            password = $('#advoc-login-form input[name=password]').val(),
            store = $('#advoc-login-form select.advoc-website-store-group option:selected').val();

        getCredentialsState(username, password, store)
        .then(
            //loginAdvocadoDashboard,
            loginToAdvocado(username, password),
            function() {
                var d = $.Deferred();
                displayError('Failed reconnecting with Advocado service. Please try again later.');
                d.reject();
                return d.promise();
            }
        )
        .then(
            function() { console.log('successful render'); },
            function() { console.log('failed render'); }
        );
    }

    /** Events on the page that would lead to account creation 
     *  on Advocado.
     */
    function createAccountEvents() { 
        $('#advoc-registration-form').submit(function(e) { 
            e.preventDefault();
            signUpState($(this));
        });
    }

    function loginFormEvents() { 
        $('#advoc-login-form').submit(loginFormSubmit);
    }


    A.loginState = loginState;
    A.signUpState = signUpState;
    A.createAccountEvents = createAccountEvents;
    A.loginFormEvents = loginFormEvents;

    return A;

})(jQuery_A, advocado || {});

jQuery_A(document).ready(function() {
    advocado.createAccountEvents();
    advocado.loginFormEvents();
});

window.advocado = advocado;

