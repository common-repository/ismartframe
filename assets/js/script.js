(function ($) {
	$(document).ready(function () {
        let apiKeyField = $('#isfwp_ismartframe_api_key');

        let spinner = $('.spinner');

        function addNotice(type, message) {
            $('.message').empty();

            let noticeClass = 'notice-error';
            switch (type) {
                case 'success': noticeClass = 'notice-success'; break;
                case 'warning': noticeClass = 'notice-warning'; break;
                default: noticeClass = 'notice-error'; break;
            }

            let noticeHtml = `
                <div class="banner notice ${noticeClass} is-dismissible isf-notice ml-0" style="margin-top: 1rem; margin-bottom: 1rem">
                    <p>${message}</p>
                </div>`;

            $('.message').append(noticeHtml);
        }

        // Simple spintf() function
        function sprintf(str) {
            let args = Array.prototype.slice.call(arguments, 1);
            return str.replace(/%(\d+)?\$?s/g, function(match, number) {
                return typeof number !== 'undefined' ? args[number - 1] : args.shift();
            });
        }

        // Check API key button click
		$("#checkApiKeyButton").on('click', function (e) {
			e.preventDefault();
            $('.message').empty();

            let api_key = apiKeyField.val();

            if (api_key.trim() === '') {
                console.log('No API key');
                addNotice('error', isfwp_object.strings.no_api_key);
                return;
            }

            spinner.addClass('is-active');

            $.ajax({
                url: isfwp_object.ajax_url,
                type: "POST",
                data: {
                    action: 'isfwp_verify_api_key',
                    nonce: isfwp_object.nonce,
                    api_key: api_key,
                },
                success: function (response) {
                    console.log(response);
                    if (response.success && response.data.checkDomain) {
                        addNotice('success', isfwp_object.strings.api_key_verified);
                        setTimeout(function () {
                            // reload the settings page
                            location.reload();
                            spinner.removeClass('is-active');
                        },  1500);
                    } else {
                        console.error("[ERROR] Error validating API key. Domain not match!");
                        addNotice('error', isfwp_object.strings.error_validating_key);
                        spinner.removeClass('is-active');
                    }
                },
                error: function (error, textStatus, errorThrown) {
                    console.error('[ERROR] AJAX Error:', error);
                    if(error.status === 400 || error.status === 401){
                        addNotice('error', isfwp_object.strings.error_validating_key);
                    } else {
                        addNotice('error', isfwp_object.strings.error_validating_key_retry);
                    }
                    spinner.removeClass('is-active');
                },
            });
		});

        // Disable the Save button when the API key input changes.
        // apiKeyField.on('change keyup paste input', function() {
        //     // submitButton.prop('disabled', true);
        // });

        // PURGE CACHE BY URL
        $('#wp-admin-bar-purgeCacheByUrl').on('click', function (e) {
            e.preventDefault();
            // $('.message').empty();

            // spinner.addClass('is-active');

            var currentUrl = window.location.href;

            console.log('currentUrl');
            console.log(currentUrl);

            $.ajax({
                url: isfwp_object.ajax_url,
                type: "POST",
                data: {
                    action: 'isfwp_purge_by_url',
                    nonce: isfwp_object.nonce,
                    urls_to_purge: [ currentUrl ]
                },
                success: function (response) {
                    // console.log(response);
                    if (response.success && response.data.result) {
                        alert(isfwp_object.strings.cache_purged_success);
                    } else {
                        alert(isfwp_object.strings.cache_purge_failed);
                    }
                },
                error: function (error, textStatus, errorThrown) {
                    console.error('AJAX Error:', error);
                    if(error.status === 400 || error.status === 401){
                        alert(isfwp_object.strings.rate_limit_exceeded);
                    } else if (error.status === 429) {
                        alert(isfwp_object.strings.cache_purge_failed);
                    } else {
                        alert(isfwp_object.strings.general_purge_error);
                    }
                },
            });
        });

        // PURGE CACHE BY URL IN EDIT MODE GUTENBERG EDITOR
        $(document).on('click', '#purge-cache-by-url-edit-gutenberg', function (e) {
            e.preventDefault();
            $('.message').empty();
            let spinner = $(this).next(".spinner");

            spinner.addClass('is-active');

            var postID = $('#purge-cache-by-url-edit-gutenberg').data('post-id');

            $.ajax({
                url: isfwp_object.ajax_url,
                type: "POST",
                data: {
                    action: 'isfwp_purge_by_url_edit',
                    nonce: isfwp_object.nonce,
                    post_id: postID
                },
                success: function (response) {
                    // console.log(response);
                    if (response.success && response.data.result) {
                        addNotice('success', isfwp_object.strings.cache_purged_success);
                        // alert('Cache successfully purged for this page.');
                    } else {
                        addNotice('error', isfwp_object.strings.cache_purge_failed);
                        // alert('Cache purged unsuccessfully, please try again later.');
                    }
                    spinner.removeClass('is-active');
                },
                error: function (error, textStatus, errorThrown) {
                    console.error('AJAX Error:', error);
                    if(error.status === 400 || error.status === 401){
                        addNotice('error', isfwp_object.strings.error_purging_cache);
                    } else if (error.status === 429) {
                        addNotice('error', error_purging_cacheisfwp_object.strings.rate_limit_exceeded);
                    } else {
                        addNotice('error', isfwp_object.strings.general_purge_error);
                    }
                    spinner.removeClass('is-active');
                },
            });
        });

        // PURGE CACHE BY URL IN EDIT MODE CLASSIC EDITOR
        $(document).on('click', '#purge-cache-by-url-edit-classic', function (e) {
            e.preventDefault();
            $('.message').empty();

            spinner.addClass('is-active');

            var postID = $('#purge-cache-by-url-edit-classic').data('post-id');

            $.ajax({
                url: isfwp_object.ajax_url,
                type: "POST",
                data: {
                    action: 'isfwp_purge_by_url_edit',
                    nonce: isfwp_object.nonce,
                    post_id: postID
                },
                success: function (response) {
                    // console.log(response);
                    if (response.success && response.data.result) {
                        addNotice('success', isfwp_object.strings.cache_purged_success);
                    } else {
                        addNotice('error', isfwp_object.strings.cache_purge_failed);
                    }
                    spinner.removeClass('is-active');
                },
                error: function (error, textStatus, errorThrown) {
                    console.error('AJAX Error:', error);
                    if(error.status === 400 || error.status === 401){
                        addNotice('error', isfwp_object.strings.error_purging_cache);
                    } else if (error.status === 429) {
                        addNotice('error', error_purging_cacheisfwp_object.strings.rate_limit_exceeded);
                    } else {
                        addNotice('error', isfwp_object.strings.general_purge_error);
                    }
                    spinner.removeClass('is-active');
                },
            });
        });

        // PURGE ALL CACHE
        $('#purgeByPatternButton').on('click', function (e) {
            e.preventDefault();
            $('#confirmationSection').show();
        });

        $('#cancelClearCache').on('click', function (e) {
            e.preventDefault();
            $('#confirmationSection').hide();
        });

        $("#confirmClearCache").on('click', function (e) {
			e.preventDefault();

            spinner.addClass('is-active');

            $.ajax({
                url: isfwp_object.ajax_url,
                type: "POST",
                data: {
                    action: 'isfwp_purge_by_pattern',
                    nonce: isfwp_object.nonce,
                },
                success: function (response) {
                    // console.log(response);
                    if (response.data.result && response.data.domain) {
                        $('#confirmationSection').hide();
                        addNotice('success', sprintf(isfwp_object.strings.cache_pattern_purged_success, response.data.domain));
                    } else {
                        console.error("[ERROR] Error clearing cache!");
                        addNotice('error', isfwp_object.strings.error_clearing_cache);
                    }
                    spinner.removeClass('is-active');
                },
                error: function (error, textStatus, errorThrown) {
                    console.error('[ERROR] AJAX Error:', error);
                    if(error.status){
                        switch (error.status){
                            case 400: addNotice('error', isfwp_object.strings.invalid_data_error); break;
                            case 401: addNotice('error', isfwp_object.strings.error_validating_key); break;
                            case 422: addNotice('error', isfwp_object.strings.invalid_regex_error); break;
                            case 429:
                                let lastPurge = error.responseJSON.data.last_purge || 'NA';
                                let remainingminutes = error.responseJSON.data.remaining_minutes || 'NA';
                                addNotice('warning', sprintf(isfwp_object.strings.wait_for_cache_reset, remainingminutes, lastPurge));
                                break;

                                default: addNotice('error', isfwp_object.strings.error_clearing_cache); break;
                        }
                    } else {
                        addNotice('error', isfwp_object.strings.error_clearing_cache);
                    }

                    spinner.removeClass('is-active');
                },
            });
		});

	});
})(jQuery);
