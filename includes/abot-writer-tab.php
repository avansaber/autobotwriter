<?php
/**
 * ABot Writer Tab Content
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

//end of code

?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<div class="aibotwriter">
    <form class="form" autocomplete="off" name="aibotwriter" id="aibotwriter">
        <div class="wizard">
            <div class="wizard-bar" style="width: 0;" data-wizard-bar></div>
            <ul class="wizard-list">
                <li class="wizard-item" data-wizard-item>1</li>
                <li class="wizard-item" data-wizard-item>2</li>
                <li class="wizard-item" data-wizard-item>3</li>
                <li class="wizard-item with-image" data-wizard-item></li>
            </ul>
        </div>
        <div class="form-content" data-form-tab>
            <h1><?php esc_html_e('Step 1: Number of blog posts', 'ai-bot-writer'); ?></h1>
            <div class="form-item" data-form-item>
                <input class="form-input" style="width:200px;" placeholder="<?php esc_html_e('Number of Blog Posts', 'ai-bot-writer'); ?>" data-form-count id="numberofposts" name="numberofblogposts" type="text" >
            </div>

            <p><?php esc_html_e('Max. 5 blog posts.', 'ai-bot-writer'); ?></p>
        </div>
        <div class="form-content" data-form-tab>
            <h1><?php esc_html_e('Step 2: Blog Titles', 'ai-bot-writer'); ?></h1>
            <div class="feedback-titles"></div>
            <div class="form-item" data-form-item>
                <select name="generate" id="generate" data-form-select id="">
                    <option value=""><?php esc_html_e('Select an option', 'ai-bot-writer'); ?></option>
                    <option value="1"><?php esc_html_e('Generate blog titles automatically', 'ai-bot-writer'); ?></option>
                    <option value="2"><?php esc_html_e('Enter blog titles manually', 'ai-bot-writer'); ?></option>
                </select>
            </div>
            <div id="the-count" style="display:none;">
                <span id="current">0</span>
                <span id="maximum">/ 500</span>
            </div>
            <div class="form-item" data-form-item>
                <textarea maxlength="500" style="display:none;" class="form-input" data-form-broad name="broaddescription" id="broaddescription" type="text" placeholder="<?php esc_html_e('Broad description (from 30 to 500 characters)', 'ai-bot-writer'); ?>"></textarea>
            </div>
            <p><?php esc_html_e('If "Generate blog titles automatically" option is selected, enter a description, and the plugin will generate  the specified number of blog topics that match the description.  If "Enter blog titles manually" option is selected, you can manually enter each blog topic.', 'ai-bot-writer'); ?></p>
        </div>
        <div class="form-content" data-form-tab>
            <h1><?php esc_html_e('Step 3: Blog Details', 'ai-bot-writer'); ?></h1>
            <div class="postinfo">
                <span>#</span>
                <span><?php esc_html_e('Post Title', 'ai-bot-writer'); ?> </span>
                <span><?php esc_html_e('Publish', 'ai-bot-writer'); ?> </span>
                <span><?php esc_html_e('Category', 'ai-bot-writer'); ?> </span>
                <span><?php esc_html_e('Author', 'ai-bot-writer'); ?> </span>
                <span><?php esc_html_e('Tags', 'ai-bot-writer'); ?>  </span>
                <span><?php esc_html_e('Include Keywords', 'ai-bot-writer'); ?> </span>
                <span><?php esc_html_e('Exclude Keywords', 'ai-bot-writer'); ?></span>
                <span></span>
            </div>
            <div id="postsinfo">

            </div>
        </div>
        <div class="form-content" data-form-tab>
            <h1><?php esc_html_e('Step 4: Confirmation', 'ai-bot-writer'); ?></h1>
            <div class="feedback-submit"></div>
            <div id="aibot_success_message"   style="display:none;"><p><?php echo __('Your posts are now in the queue for automatic generation!','aibotw'); ?></p></div>
            <?php
            list($y,$m) = explode('-', date('Y-m'));
            $option_index = 'autobotwriter_gen_'.$m.'-'.$y;
            $opt = get_option($option_index,0);
            if($opt>=5){ ?>
                <div id="aibot_limit_message" class="notice notice-error"  ><p><?php echo __('You have reached the monthly limit. Upgrade to the Pro version for more articles.','aibotw'); ?></p></div>
            <?php } ?>
            <div id="postsinfopreview">
                <strong><?php esc_html_e('Number of blog posts:', 'ai-bot-writer'); ?> </strong><span id="numberconf"></span><br/>
                <strong><?php esc_html_e('Chosen Method:', 'ai-bot-writer'); ?> </strong><span id="methodconf"></span><br/>
                <span id="broadconf"></span>
                <strong><?php esc_html_e('Posts Information:', 'ai-bot-writer'); ?></strong><br/><br/>
                <table class="wp-list-table widefat fixed striped table-view-list ">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Post Title', 'ai-bot-writer'); ?></th>
                        <th><?php esc_html_e('Publish Date', 'ai-bot-writer'); ?></th>
                        <th><?php esc_html_e('Category', 'ai-bot-writer'); ?></th>
                        <th><?php esc_html_e('Author', 'ai-bot-writer'); ?></th>
                        <th><?php esc_html_e('Tags', 'ai-bot-writer'); ?></th>
                        <th><?php esc_html_e('Include Keywords', 'ai-bot-writer'); ?></th>
                        <th><?php esc_html_e('Exclude Keywords', 'ai-bot-writer'); ?></th>
                    </tr>
                    </thead>
                    <tbody id="postsconfirmation"></tbody></table>
            </div>
        </div>
        <div class="form-buttons">
            <button class="button" type="button" data-btn-previous="true"><?php esc_html_e('Return', 'ai-bot-writer'); ?></button>
            <?php if(isset($options['openai_api_key']) && trim($options['openai_api_key'])!=''): ?>
                <button class="button-primary" type="button" data-btn-next="true"><?php esc_html_e('Next', 'ai-bot-writer'); ?></button>

                <img id="spinning" src="<?php echo site_url('wp-admin/images/spinner.gif'); ?>" style="display:none;width:20px;height: 20px;">
            <?php else: ?>
                <p><?php esc_html_e('Please go to Settings Tab and input your OpenAI key and select a model before using this wizard.','ai-bot-writer'); ?></p>
            <?php endif;?>
        </div>
        <span id="broaddescriptionlabel" style="display:none;"><?php esc_html_e('Broad Description', 'ai-bot-writer'); ?></span>
        <span id="generatelabel" style="display:none;"><?php esc_html_e('Generate!', 'ai-bot-writer'); ?></span>
        <span id="nextlabel" style="display:none;"><?php esc_html_e('Next', 'ai-bot-writer'); ?></span>
    </form>
    <div id="rowpoststemplate" style="display:none;">

        <div class="postinfo">
            <span class="count"></span>
            <input class="aibotpost-title" name="title[]" data-form-input id="titleINDEX" type="text" placeholder="<?php esc_html_e('Post Title', 'ai-bot-writer'); ?>">
            <input class="aibotpost-date" name="date[]"  id="dateINDEX" type="text" placeholder="<?php esc_html_e('Publish', 'ai-bot-writer'); ?>">
            <select class="aibotpost-category" name="category[]"   id="categoryINDEX">
                CATS
            </select>
            <select class="aibotpost-author" name="author[]"  data-form-select id="authorINDEX" type="text" placeholder="<?php esc_html_e('Author', 'ai-bot-writer'); ?>">
                USERSDROPDOWN
            </select>
            <select class="aibotpost-tags" name="tags[INDEX][]" multiple  id="tagsINDEX" >
                TAGGING
            </select>
            <input class="aibotpost-include" name="include[]"   id="includeINDEX" type="text" placeholder="<?php esc_html_e('Include Keywords', 'ai-bot-writer'); ?>">
            <input class="aibotpost-exclude" name="exclude[]"   id="excludeINDEX" type="text" placeholder="<?php esc_html_e('Exclude Keywords', 'ai-bot-writer'); ?>">
            <span><i class="fa fa-close fa-times" onclick="jQuery(this).closest('.postinfo').remove();loadPreview();"></i></span>
        </div>
    </div>
</div>