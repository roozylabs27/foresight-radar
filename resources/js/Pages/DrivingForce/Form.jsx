import { Form, Input, Select } from "antd";
import TextArea from "antd/es/input/TextArea";
import React, { useEffect, useImperativeHandle, forwardRef } from "react";

const FormDrivingForce = forwardRef(
    (
        {
            initialValues,
            isEditMode,
            dimensions,
            status,
            errors,
            onFinish,
            loading,
        },
        ref
    ) => {
        const [form] = Form.useForm();

        useEffect(() => {
            if (isEditMode) {
                form.setFieldsValue(initialValues);
            } else {
                form.resetFields();
            }
        }, [isEditMode, initialValues, form]);

        // Expose form submit function to parent component
        useImperativeHandle(ref, () => ({
            submit: () => {
                form.submit();
            },
            reset: () => {
                form.setFieldsValue(initialValues);
            },
        }));

        // const validateKeywordLength = (_, value) => {
        //     if (!value || value.length >= 5) {
        //         return Promise.resolve();
        //     }
        //     return Promise.reject(
        //         new Error("Keyword must be at least 5 characters long")
        //     );
        // };

        return (
            <Form
                form={form}
                name="basic"
                disabled={loading}
                initialValues={initialValues}
                layout="vertical"
                onFinish={onFinish}
            >
                <Form.Item
                    name="keyword"
                    label="Keyword"
                    validateStatus={errors?.keyword ? "error" : ""}
                    help={errors?.keyword}
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please input the keyword!",
                        },
                    ]}
                >
                    <Input
                        placeholder="Enter keyword"
                        autoFocus={isEditMode ? false : true}
                    />
                </Form.Item>
                <Form.Item
                    name="description"
                    label="Description"
                    validateTrigger="onBlur"
                    validateStatus={errors?.description ? "error" : ""}
                    help={errors?.description}
                    rules={[
                        {
                            required: true,
                            message: "Please input the description!",
                        },
                    ]}
                >
                    <Input placeholder="Enter description" />
                </Form.Item>
                <Form.Item
                    name="dimension_id"
                    label="Dimension"
                    validateTrigger="onBlur"
                    rules={[
                        {
                            required: true,
                            message: "Please select the dimension!",
                        },
                    ]}
                >
                    <Select
                        style={{ width: "100%" }}
                        placeholder="Select a dimension"
                        options={dimensions}
                    />
                </Form.Item>
                {isEditMode && (
                    <>
                        <Form.Item
                            name="status"
                            validateTrigger="onBlur"
                            label="Status"
                            rules={[
                                {
                                    required: true,
                                    message: "Please select the status!",
                                },
                            ]}
                        >
                            <Select
                                style={{ width: "100%" }}
                                placeholder="Select a Status"
                                options={status}
                            />
                        </Form.Item>
                        <Form.Item name="remark" label="Remark">
                            <TextArea
                                placeholder="Enter a remark"
                                autoSize={{ minRows: 3, maxRows: 5 }}
                            />
                        </Form.Item>
                    </>
                )}
            </Form>
        );
    }
);

export default FormDrivingForce;
