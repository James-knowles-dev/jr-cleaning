 (function(){
                // Resize the YouTube background iframe so it always "covers" the hero container.
                function resizeHeroIframe(container) {
                    var wrap = container.querySelector('.hero-block__video-wrap');
                    if (!wrap) return;
                    var iframe = wrap.querySelector('iframe');
                    if (!iframe) return;

                    var rect = wrap.getBoundingClientRect();
                    var cw = rect.width;
                    var ch = rect.height;
                    var aspect = 16 / 9;

                    // Calculate dimensions that will cover the container while preserving aspect ratio
                    var requiredWidth = Math.max(cw, ch * aspect);
                    var requiredHeight = Math.max(ch, cw / aspect);

                    // Apply size and keep it centered via transform
                    iframe.style.width = requiredWidth + 'px';
                    iframe.style.height = requiredHeight + 'px';
                    iframe.style.top = '50%';
                    iframe.style.left = '50%';
                    iframe.style.transform = 'translate(-50%, -50%)';
                    iframe.style.position = 'absolute';
                }

                function initAll() {
                    var blocks = document.querySelectorAll('.hero-block.has-background-video');
                    blocks.forEach(function(block){
                        // run once now
                        resizeHeroIframe(block);
                        // update on resize
                        window.addEventListener('resize', function(){ resizeHeroIframe(block); });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initAll);
                } else {
                    initAll();
                }
            })();