import { Form, Input, Select } from "antd";
import { useEffect, useState } from "react";

export default function FormDrivingForce({
    initialValues,
    isEditMode,
    dimensions,
    onFinish,
}) {
    const [form] = Form.useForm();
    const [formData, setFormData] = useState({
        keyword: {
            name: "keyword",
            label: "Keyword",
            placeholder: "Enter keyword",
            rules: [
                {
                    required: true,
                    message: "Please input the keyword!",
                },
            ],
            value: "",
        },
        description: {
            name: "description",
            label: "Description",
            placeholder: "Enter description",
            rules: [
                {
                    required: true,
                    message: "Please input the description!",
                },
            ],
            value: "",
        },
        dimension: {
            name: "dimension",
            label: "Dimension",
            placeholder: "Select a dimension",
            rules: [
                {
                    required: true,
                    message: "Please select the dimension !",
                },
            ],
            value: "",
        },
        status: {
            name: "status",
            label: "Status",
            placeholder: "Select a Status",
            rules: [
                {
                    required: true,
                    message: "Please select the status!",
                },
            ],
            value: "",
        },
    });

    useEffect(() => {
        if (isEditMode) {
            form.setFieldsValue(initialValues);
        } else {
            form.resetFields();
        }
    }, [isEditMode, initialValues, form]);

    return (
        <>
            <Form
                form={form}
                name="basic"
                initialValues={initialValues}
                layout="vertical"
                onFinish={onFinish}
            >
                <Form.Item
                    name={formData.keyword.name}
                    label={formData.keyword.label}
                    rules={formData.keyword.rules}
                >
                    <Input
                        placeholder={formData.keyword.placeholder}
                        onChange={(e) =>
                            setFormData((prevState) => ({
                                ...prevState,
                                keyword: {
                                    ...prevState.keyword,
                                    value: e.target.value,
                                },
                            }))
                        }
                    />
                </Form.Item>
                <Form.Item
                    name={formData.description.name}
                    label={formData.description.label}
                    rules={formData.description.rules}
                >
                    <Input
                        placeholder={formData.description.placeholder}
                        onChange={(e) =>
                            setFormData((prevState) => ({
                                ...prevState,
                                description: {
                                    ...prevState.description,
                                    value: e.target.value,
                                },
                            }))
                        }
                    />
                </Form.Item>
                <Form.Item
                    name={formData.dimension.name}
                    label={formData.dimension.label}
                    rules={formData.dimension.rules}
                >
                    <Select
                        style={{
                            width: "100%",
                        }}
                        placeholder={formData.dimension.placeholder}
                        onChange={(e) =>
                            setFormData((prevState) => ({
                                ...prevState,
                                dimension: {
                                    ...prevState.dimension,
                                    value: e.target.value,
                                },
                            }))
                        }
                        options={dimensions}
                    />
                </Form.Item>
                <Form.Item
                    name={formData.status.name}
                    label={formData.status.label}
                    rules={formData.status.rules}
                >
                    <Select
                        style={{
                            width: "100%",
                        }}
                        placeholder={formData.status.placeholder}
                        onChange={(e) =>
                            setFormData((prevState) => ({
                                ...prevState,
                                status: {
                                    ...prevState.status,
                                    value: e.target.value,
                                },
                            }))
                        }
                        options={[
                            {
                                label: "pending",
                                value: "PENDING",
                            },
                            {
                                label: "approved",
                                value: "APPROVED",
                            },
                            {
                                label: "rejected",
                                value: "REJECTED",
                            },
                        ]}
                    />
                </Form.Item>
            </Form>
        </>
    );
}
