<?php
/**
 * Settings Tab Content
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
 

?>

<div id="settings" class="ai-bot-writer-tab-content-item">
    <h2><?php esc_html_e('Settings', 'ai-bot-writer'); ?></h2>

    <form method="post" id="plugin-settings-form" action=""> 
        <div class="feedback">
            <div class="success" style="display: none;"><p></p><?=  __('Your settings were saved sucessfully.','ai-bot-writer') ?></p></div>
            <div class="failure" style="display: none;"><p><?=  __('There was an error saving your settings.','ai-bot-writer') ?><span class="errordetails"></span></p></div>
        </div>
        <?php wp_nonce_field('ai-bot-writer-sync-models', 'ai_bot_writer_sync_models_nonce'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('OpenAI API Key', 'ai-bot-writer');   ?>
                    <span class="ai-bot-writer-tooltip" data-tippy-content="<?php esc_attr_e('Enter your OpenAI API key here.', 'ai-bot-writer'); ?>">?</span>
                </th>
                <td>
                    <input type="text" name="openai_api_key" id="openai_api_key" value="<?php echo $options['openai_api_key']; ?>" />
                   <!-- <button type="submit" class="button ai-bot-writer-sync-button" name="ai_bot_writer_sync_models" id="ai-bot-writer-sync-button"><?php esc_html_e('Sync Models', 'ai-bot-writer'); ?></button>-->
                    <p class="description">
                        <strong>Where do I find my Secret Open-AI API Key? </strong>You can find your API key in your <a href="https://beta.openai.com/account/api-keys" rel="noopener noreferrer nofollow" target="_blank"><u>user settings</u></a>. Here is the <a href="https://www.youtube.com/watch?v=EQQjdwdVQ-M" rel="noopener noreferrer nofollow" target="_blank">help video</a> on how to get Open-AI API key.
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Tokens', 'ai-bot-writer'); ?>
                    <span class="ai-bot-writer-tooltip" data-tippy-content="<?php esc_attr_e('Enter the amount of tokens per request.', 'ai-bot-writer'); ?>">?</span>
                </th>
                <td>
                    <input type="text" name="openai_tokens" id="openai_tokens" value="<?php echo $options['tokens']; ?>" /> 
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Temperature', 'ai-bot-writer'); ?>
                    <span class="ai-bot-writer-tooltip" data-tippy-content="<?php esc_attr_e('Enter the temperature per request.', 'ai-bot-writer'); ?>">?</span>
                </th>
                <td>
                    <input type="text" name="openai_temperature" id="openai_temperature" value="<?php echo $options['temperature']; ?>" /> 
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Number of Headings per Article', 'ai-bot-writer'); ?>
                    <span class="ai-bot-writer-tooltip" data-tippy-content="<?php esc_attr_e('Enter the number of headings to be generated per article.', 'ai-bot-writer'); ?>">?</span>
                </th>
                <td>
                    <input type="text" name="openai_headings" id="openai_headings" value="<?php echo $options['headings']; ?>" /> 
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Preferred Model', 'ai-bot-writer'); ?>
                    <span class="ai-bot-writer-tooltip" data-tippy-content="<?php esc_attr_e('Select your preferred model for generating blog posts.', 'ai-bot-writer'); ?>">?</span>
                </th>
                <td>
                    <select <?= trim($options['openai_api_key'])=='' ? 'disabled' : ''; ?> name="ai_bot_writer_preferred_model" id="ai_bot_writer_preferred_model">
                        <?php

                        $available_models = [];
                        $opt = get_option('autobotwriter_models',false);
                        if($opt)
                            foreach ($opt as $key => $value) {
                                $available_models[]= $value['id'];
                            }
                        else
                            $available_models = ['text-davinci-003', 'text-davinci-002', 'davinci', 'curie', 'babbage', 'ada','gpt-4-32k','gpt-4','gpt-3.5-turbo'];

                        $preferred_model = $options['selected_model'];

                        foreach ($available_models as $model) {
                            printf('<option value="%s" %s>%s</option>', esc_attr($model), $options['selected_model'] == $model ? 'selected' : '', esc_html($model));
                        }
                        ?>
                    </select>
                    <p class="ai-bot-writer-model-recommendation"><?php esc_html_e('We recommend using the "gpt-4" model for optimal results.', 'ai-bot-writer'); ?></p>
                </td>
            </tr>
        </table>

            <input class="button button-primary" type="submit" <?php echo trim($options['openai_api_key'])==''? 'disabled' : ''; ?> value="Save Settings"> 
    </form>
</div>
