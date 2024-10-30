document.addEventListener('DOMContentLoaded', function() {

    (function(wp) {
        const { registerPlugin } = wp.plugins;
        const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
        const { PanelBody, Button } = wp.components;
        const { Fragment } = wp.element;
        const { createElement } = wp.element;
        const { useDispatch, select } = wp.data;

        const MyCustomButton = () => {
            const editPost = wp.data.select('core/editor');
            const postId = editPost.getCurrentPostId();

            return (
                wp.element.createElement(
                    Fragment,
                    null,
                    wp.element.createElement(
                        PluginSidebarMoreMenuItem,
                        {
                            target: "my-custom-sidebar"
                        },
                        isfwp_object_strings.purge_from_cache
                    ),
                    wp.element.createElement(
                        PluginSidebar,
                        {
                            name: "my-custom-sidebar",
                            title: isfwp_object_strings.purge_from_cache,
                            icon:  "performance",
                        },
                        wp.element.createElement(
                            PanelBody,
                            null,
                            wp.element.createElement(
                                'div',
                                { className: 'message' },
                                null
                            ),
                            createElement(
                                'p', // Add a text element above the button
                                null,
                                isfwp_object_strings.confirm_purge_message
                            ),
                            wp.element.createElement(
                                Button,
                                {

                                    id: 'purge-cache-by-url-edit-gutenberg',
                                    isPrimary: true,
                                    'data-post-id': postId,
                                },
                                isfwp_object_strings.yes
                            ),
                            wp.element.createElement(
                                'div',
                                { className: 'spinner' },
                                null
                            )
                        )
                    )
                )
            )
        };

        registerPlugin('my-custom-button', { render: MyCustomButton });

    })(window.wp);

});